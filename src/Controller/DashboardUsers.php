<?php

namespace App\Controller;

use App\Entity\Associated;
use App\Entity\Company;
use App\Entity\Delivery;
use App\Entity\Driver;
use App\Entity\FreightHauler;
use App\Entity\ImportRequest;
use App\Entity\User;
use App\Entity\Vehicle;
use App\Notification\UserStatusMailer;
use App\Workflow\ImportRequestWorkflow;
use App\Workflow\TransportCoordinator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class DashboardUsers extends AbstractController {
	use AjaxCsrfTrait;

	/**
	 * Estatus, en el orden en que normalmente se recorren, para presentar el
	 * avance de los expedientes activos en el Inicio del ejecutivo. Junta las
	 * cuatro secuencias de ImportRequestWorkflow en un solo orden razonable;
	 * no hace falta que sea exacto por direccion/tipo, solo consistente.
	 */
	private const STATUS_DISPLAY_ORDER = [
		ImportRequestWorkflow::PENDING,
		ImportRequestWorkflow::CAPTURED,
		ImportRequestWorkflow::DECONSOLIDATED,
		ImportRequestWorkflow::ENTERED,
		ImportRequestWorkflow::REVALIDATED,
		ImportRequestWorkflow::RELEASED_AT_TERMINAL,
		ImportRequestWorkflow::PAID,
		ImportRequestWorkflow::SCHEDULED,
		ImportRequestWorkflow::MODULATED,
		ImportRequestWorkflow::OFFSITE_INSPECTION,
		ImportRequestWorkflow::IN_TRANSIT,
		ImportRequestWorkflow::DELIVERED,
		ImportRequestWorkflow::EMPTY_RETURNED,
	];

	#[Route(name: 'dashboard', path: '/dashboard')]
	public function dashboard(
		EntityManagerInterface $entityManager,
		ImportRequestWorkflow $workflow,
		TransportCoordinator $coordinator,
	): Response {
		/** @var User $user */
		$user = $this->getUser();

		// ROLE_ADMIN inherits ROLE_EXECUTIVE (see role_hierarchy in security.yaml)
		if ($this->isGranted('ROLE_EXECUTIVE')) {
			return $this->staffDashboard($entityManager, $workflow, $coordinator, $user);
		}

		if ($this->isGranted('ROLE_FH')) {
			return $this->haulerDashboard($entityManager, $coordinator, $user);
		}

		return $this->clientDashboard($entityManager, $workflow, $user);
	}

	/**
	 * Inicio del ejecutivo (y del admin, que lo hereda): lo que le toca hacer
	 * hoy, el avance general de la cartera y las alertas de operacion. El
	 * admin ve ademas, arriba, lo que tiene pendiente de validar.
	 */
	private function staffDashboard(
		EntityManagerInterface $entityManager,
		ImportRequestWorkflow $workflow,
		TransportCoordinator $coordinator,
		User $user,
	): Response {
		$active = $entityManager->getRepository(ImportRequest::class)->findActive();

		$attentionQueue = [];

		foreach ($active as $import) {
			$reason = $this->attentionReasonFor($import, $workflow);

			if ($reason !== null) {
				$attentionQueue[] = ['import' => $import, 'reason' => $reason];
			}
		}

		$attentionTotal = count($attentionQueue);
		$attentionQueue = array_slice($attentionQueue, 0, 8);

		$statusCounts = [];

		foreach ($active as $import) {
			$statusCounts[$import->getStatus()] = ($statusCounts[$import->getStatus()] ?? 0) + 1;
		}

		$orderedStatusCounts = [];

		foreach (self::STATUS_DISPLAY_ORDER as $status) {
			if (!empty($statusCounts[$status])) {
				$orderedStatusCounts[$status] = $statusCounts[$status];
			}
		}

		$today = new \DateTimeImmutable('today');
		$todayDeliveries = $entityManager->getRepository(Delivery::class)->findByDate($today);

		$unconfirmedToday = 0;

		foreach ($todayDeliveries as $delivery) {
			if (!$delivery->isDeparted() && !$delivery->isFailed()) {
				++$unconfirmedToday;
			}
		}

		$pendingReturns = 0;
		$scheduledAwaitingModulation = 0;

		foreach ($active as $import) {
			$pendingReturns += count($coordinator->containersPendingReturn($import));

			if ($import->getStatus() === ImportRequestWorkflow::SCHEDULED) {
				++$scheduledAwaitingModulation;
			}
		}

		$data = [
			'name' => $user->getName(),
			'role' => $user->getRoles()[0],
			'loged' => 'true',
			'activeTotal' => count($active),
			'attentionQueue' => $attentionQueue,
			'attentionTotal' => $attentionTotal,
			'statusCounts' => $orderedStatusCounts,
			'todayDeliveries' => array_slice($todayDeliveries, 0, 6),
			'unconfirmedToday' => $unconfirmedToday,
			'pendingReturns' => $pendingReturns,
			'scheduledAwaitingModulation' => $scheduledAwaitingModulation,
		];

		if ($this->isGranted('ROLE_ADMIN')) {
			$pendingUsers = $entityManager->getRepository(User::class)->findBy(['status' => 'pending'], ['id' => 'DESC']);
			$pendingAssociations = $entityManager->getRepository(Associated::class)->findBy(['status' => Associated::PENDING], ['id' => 'DESC']);

			$data['pendingUsers'] = array_slice($pendingUsers, 0, 5);
			$data['pendingUsersTotal'] = count($pendingUsers);
			$data['pendingAssociations'] = array_slice($pendingAssociations, 0, 5);
			$data['pendingAssociationsTotal'] = count($pendingAssociations);
		}

		return $this->render('/dashboard/admin.html.twig', $data);
	}

	/**
	 * Que le falta a este expediente para que el EJECUTIVO tenga que hacer
	 * algo — no lo que le falta al transportista o al SOIA, eso no le
	 * corresponde a esta cola. Null significa que, aunque siga activo, no
	 * necesita nada de la agencia ahora mismo (esta esperando a alguien mas).
	 *
	 * Replica el mismo criterio que ya usa el panel "Avance" del expediente
	 * (ver caseFile.html.twig): un estatus sin entrada en DOCUMENT_GATES (p.
	 * ej. "En tránsito" o "Entregado") lo dispara el transportista, y el
	 * boton manual de avanzar que sigue existiendo ahi es solo para casos
	 * extraordinarios, no para el uso diario.
	 */
	private function attentionReasonFor(ImportRequest $import, ImportRequestWorkflow $workflow): ?string {
		if ($import->travelsWithConsolidator() && $import->getConsolidatorInstructions()->isEmpty()) {
			return 'Viaja con consolidador (XCF): falta enviar instrucciones.';
		}

		if ($import->getStatus() === ImportRequestWorkflow::PENDING) {
			$missing = $workflow->missingRequirements($import, ImportRequestWorkflow::CAPTURED);

			return $missing !== []
				? sprintf('Falta para dar de alta el pedimento: %s.', implode(', ', $missing))
				: 'Listo para dar de alta el pedimento.';
		}

		foreach ($workflow->nextStatuses($import) as $target) {
			if ($target === ImportRequestWorkflow::OFFSITE_INSPECTION && !$workflow->offsiteInspectionExpected($import)) {
				continue;
			}

			if ($target === ImportRequestWorkflow::MODULATED) {
				return $import->getImportNumber() && $import->getImportNumber() !== 'Pendiente'
					? 'Esperando modulación: puedes forzar la consulta al SOIA.'
					: 'Falta el número de pedimento para poder consultar el SOIA.';
			}

			if ($target === ImportRequestWorkflow::FINISHED) {
				return $import->getInternInvoices()->isEmpty() ? 'Falta anexar la cuenta de gastos para poder finalizar.' : null;
			}

			if ($target === ImportRequestWorkflow::SCHEDULED) {
				if ($workflow->missingRequirements($import, ImportRequestWorkflow::SCHEDULED) !== []) {
					return 'Falta agendar la cita (o subir el comprobante) para programar el despacho.';
				}

				return $import->getDeliveries()->isEmpty() ? 'Listo para avisar al transporte.' : null;
			}

			$missing = $workflow->missingRequirements($import, $target);

			if ($missing !== []) {
				return sprintf('Falta para "%s": %s.', $target, implode(', ', $missing));
			}

			// Sin requisito pendiente y sin caso especial: lo dispara el
			// transportista o es un paso opcional, no la agencia.
			return null;
		}

		return null;
	}

	/**
	 * Inicio del cliente: sus expedientes activos, que tan avanzados van y
	 * que le toca hacer a el (no al ejecutivo) en cada uno.
	 */
	private function clientDashboard(EntityManagerInterface $entityManager, ImportRequestWorkflow $workflow, User $user): Response {
		$companies = $entityManager->getRepository(Company::class)->findAssociatedCompanies($user);
		$active = $entityManager->getRepository(ImportRequest::class)->findActiveForCompanies($companies);

		$imports = [];
		$actionNeeded = 0;
		$upcomingDelivery = null;
		$now = new \DateTimeImmutable('today');

		foreach ($active as $import) {
			$flag = $import->isEtaConfirmed() ? null : 'Confirma la fecha estimada de llegada (ETA).';

			if ($flag !== null) {
				++$actionNeeded;
			}

			$imports[] = ['import' => $import, 'progress' => $workflow->progress($import), 'flag' => $flag];

			foreach ($import->getDeliveries() as $delivery) {
				if ($delivery->isDelivered() || $delivery->isFailed() || $delivery->getDate() < $now) {
					continue;
				}

				if ($upcomingDelivery === null
					|| [$delivery->getDate(), $delivery->getHour()] < [$upcomingDelivery->getDate(), $upcomingDelivery->getHour()]
				) {
					$upcomingDelivery = $delivery;
				}
			}
		}

		return $this->render('/dashboard/client.html.twig', [
			'name' => $user->getName(),
			'role' => $user->getRoles()[0],
			'loged' => 'true',
			'imports' => $imports,
			'activeTotal' => count($active),
			'actionNeeded' => $actionNeeded,
			'upcomingDelivery' => $upcomingDelivery,
			'companies' => $companies,
		]);
	}

	/**
	 * Inicio del transportista: los despachos de hoy, lo que le falta
	 * asignar y el estado de su flota.
	 */
	private function haulerDashboard(EntityManagerInterface $entityManager, TransportCoordinator $coordinator, User $user): Response {
		$haulers = $entityManager->getRepository(FreightHauler::class)->findBy(['id_user' => $user]);

		if ($haulers === []) {
			return $this->render('/dashboard/haulerHome.html.twig', [
				'name' => $user->getName(),
				'role' => $user->getRoles()[0],
				'loged' => 'true',
				'haulers' => [],
			]);
		}

		$repository = $entityManager->getRepository(Delivery::class);
		$delivered = $repository->findBy(['transport' => $haulers]);
		$returning = $repository->findBy(['returnTransport' => $haulers]);

		$byId = [];

		foreach (array_merge($delivered, $returning) as $delivery) {
			$byId[$delivery->getId()] = $delivery;
		}

		$deliveries = array_values($byId);
		usort($deliveries, static fn (Delivery $a, Delivery $b): int => [$a->getDate(), $a->getHour()] <=> [$b->getDate(), $b->getHour()]);

		$today = new \DateTimeImmutable('today');
		$todayDeliveries = array_values(array_filter($deliveries, static fn (Delivery $d): bool => $d->getDate() == $today));

		$pendingReturnsTotal = 0;
		$missingVehicle = 0;

		foreach ($deliveries as $delivery) {
			$pendingReturnsTotal += count($coordinator->containersPendingReturnFor($delivery));

			if (in_array($delivery->getTransport(), $haulers, true) && !$delivery->isDeparted() && !$delivery->isFailed() && $delivery->getVehicle() === null) {
				++$missingVehicle;
			}
		}

		$vehicleTotal = 0;
		$driverTotal = 0;

		foreach ($haulers as $hauler) {
			$vehicleTotal += $entityManager->getRepository(Vehicle::class)->count(['hauler' => $hauler]);
			$driverTotal += $entityManager->getRepository(Driver::class)->count(['hauler' => $hauler]);
		}

		return $this->render('/dashboard/haulerHome.html.twig', [
			'name' => $user->getName(),
			'role' => $user->getRoles()[0],
			'loged' => 'true',
			'haulers' => $haulers,
			'todayDeliveries' => $todayDeliveries,
			'pendingReturnsTotal' => $pendingReturnsTotal,
			'missingVehicle' => $missingVehicle,
			'vehicleTotal' => $vehicleTotal,
			'driverTotal' => $driverTotal,
		]);
	}

	#[Route(name: 'users', path: '/dashboard/usuarios')]
	#[IsGranted('ROLE_ADMIN')]
	public function users(EntityManagerInterface $entityManager): Response {
		/** @var User $user */
		$user = $this->getUser();
		$users = $entityManager->getRepository(User::class)->findAll();

		return $this->render("/dashboard/users.html.twig", [
			'name' => $user->getName(),
			'role' => $user->getRoles()[0],
			'loged' => 'true',
			'users' => $users
		]);
	}

	#[Route(name: 'verifyUser', path: '/dashboard/usuarios/{id}/verificar', methods: ['POST'])]
	#[IsGranted('ROLE_ADMIN')]
	public function verifyUser(int $id, EntityManagerInterface $entityManager, Request $r, UserStatusMailer $mailer): JsonResponse {
    if ($csrf = $this->rejectInvalidAjaxCsrf($r)) {
      return $csrf;
    }

    if (!$r->isXmlHttpRequest()) {
      return new JsonResponse(['success' => false, 'message' => 'Petición no válida'], 400);
    }

    $user = $entityManager->getRepository(User::class)->find($id);

    if (!$user) {
			return new JsonResponse(['success' => false, 'message' => 'Usuario no encontrado'], 404);
    }

    $user->setStatus('active');
    $entityManager->flush();
    $mailer->notifyApproved($user);

    return new JsonResponse(['success' => true, 'message' => 'Usuario verificado con éxito']);
	}

	#[Route(name: 'denyUser', path: '/dashboard/usuarios/{id}/rechazar', methods: ['POST'])]
	#[IsGranted('ROLE_ADMIN')]
	public function denyUser(int $id, EntityManagerInterface $entityManager, Request $r, UserStatusMailer $mailer): JsonResponse {
    if ($csrf = $this->rejectInvalidAjaxCsrf($r)) {
      return $csrf;
    }

    if (!$r->isXmlHttpRequest()) {
      return new JsonResponse(['success' => false, 'message' => 'Petición no válida'], 400);
    }

    $user = $entityManager->getRepository(User::class)->find($id);

    if (!$user) {
      return new JsonResponse(['success' => false, 'message' => 'Usuario no encontrado'], 404);
    }

    $user->setStatus('inactive');
    $entityManager->flush();
    $mailer->notifyRejected($user);

    return new JsonResponse(['success' => true, 'message' => 'Usuario rechazado con éxito']);
	}

	#[Route(name: 'disableUser', path: '/dashboard/usuarios/{id}/deshabilitar', methods: ['POST'])]
	#[IsGranted('ROLE_ADMIN')]
	public function disableUser(int $id, Request $r, EntityManagerInterface $entityManager): Response {
    if ($csrf = $this->rejectInvalidAjaxCsrf($r)) {
      return $csrf;
    }

		$user = $entityManager->getRepository(User::class)->find($id);

		if (!$user) {
			return new JsonResponse(['success' => false, 'message' => 'Usuario no encontrado'], 404);
		}

		$user->setStatus('pending');
		$entityManager->flush();

		return new JsonResponse(['success' => true, 'message' => 'Usuario deshabilitado correctamente']);
	}

	#[Route(name: 'enableUser', path: '/dashboard/usuarios/{id}/habilitar', methods: ['POST'])]
	#[IsGranted('ROLE_ADMIN')]
	public function enableUser(int $id, Request $r, EntityManagerInterface $entityManager): Response {
    if ($csrf = $this->rejectInvalidAjaxCsrf($r)) {
      return $csrf;
    }

		$user = $entityManager->getRepository(User::class)->find($id);

		if (!$user) {
			return new JsonResponse(['success' => false, 'message' => 'Usuario no encontrado'], 404);
		}

		$user->setStatus('active');
		$entityManager->flush();

		return new JsonResponse(['success' => true, 'message' => 'Usuario reactivado correctamente', 'user_id' => $user->getId()
    ]);
	}

	#[Route(name: 'editUser', path: '/dashboard/usuarios/{id}/editar', methods: ['POST'])]
	#[IsGranted('ROLE_ADMIN')]
  public function editUser(int $id, Request $r, EntityManagerInterface $entityManager ): JsonResponse {
    if ($csrf = $this->rejectInvalidAjaxCsrf($r)) {
      return $csrf;
    }

    $user = $entityManager->getRepository(User::class)->find($id);

    if (!$user) {
      return new JsonResponse(['success' => false, 'message' => 'Usuario no encontrado.'], 404);
    }

    $data = json_decode($r->getContent(), true);

    $name = $data['name'] ?? null;
    $lastName = $data['lastName'] ?? null;
    $email = $data['email'] ?? null;

    if (!$name || !$lastName || !$email) {
      return new JsonResponse(['success' => false, 'message' => 'Faltan campos obligatorios.'], 400);
    }

    // Validar formato del email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      return new JsonResponse(['success' => false, 'message' => 'El email no es válido.'], 400);
    }

    $user->setName($name);
    $user->setLastName($lastName);
    $user->setEmail($email);

    $entityManager->persist($user);
    $entityManager->flush();

    return new JsonResponse(['success' => true]);
  }

  #[Route(name: 'changeRole', path: '/dashboard/usuarios/{id}/cambiarRol', methods: ['POST'])]
  #[IsGranted('ROLE_ADMIN')]
  public function changeRole(int $id, Request $r, EntityManagerInterface $entityManager): JsonResponse {
    if ($csrf = $this->rejectInvalidAjaxCsrf($r)) {
      return $csrf;
    }

    $user = $entityManager->getRepository(User::class)->find($id);

    if (!$user) {
      return new JsonResponse(['success' => false, 'message' => 'Usuario no encontrado.'], 404);
    }

    $data = json_decode($r->getContent(), true);
    $newRole = $data['newRole'] ?? null;

    $validRoles = ['ROLE_ADMIN', 'ROLE_EXECUTIVE', 'ROLE_CLIENT', 'ROLE_FH'];

    if (!$newRole || !in_array($newRole, $validRoles)) {
      return new JsonResponse(['success' => false, 'message' => 'Rol inválido.'], 400);
    }

    $user->setRoles([$newRole]);
    $entityManager->persist($user);
    $entityManager->flush();

    return new JsonResponse(['success' => true]);
  }
}
