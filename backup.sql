--
-- PostgreSQL database dump
--

-- Dumped from database version 16.4
-- Dumped by pg_dump version 16.4

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Name: notify_messenger_messages(); Type: FUNCTION; Schema: public; Owner: app
--

CREATE FUNCTION public.notify_messenger_messages() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
    BEGIN
        PERFORM pg_notify('messenger_messages', NEW.queue_name::text);
        RETURN NEW;
    END;
$$;


ALTER FUNCTION public.notify_messenger_messages() OWNER TO app;

SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: associated; Type: TABLE; Schema: public; Owner: app
--

CREATE TABLE public.associated (
    id integer NOT NULL,
    id_client_id integer NOT NULL,
    id_company_id integer NOT NULL
);


ALTER TABLE public.associated OWNER TO app;

--
-- Name: associated_id_seq; Type: SEQUENCE; Schema: public; Owner: app
--

CREATE SEQUENCE public.associated_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.associated_id_seq OWNER TO app;

--
-- Name: associated_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: app
--

ALTER SEQUENCE public.associated_id_seq OWNED BY public.associated.id;


--
-- Name: company; Type: TABLE; Schema: public; Owner: app
--

CREATE TABLE public.company (
    id integer NOT NULL,
    name character varying(255) NOT NULL,
    address character varying(255) NOT NULL,
    rfc character varying(255) NOT NULL
);


ALTER TABLE public.company OWNER TO app;

--
-- Name: company_document; Type: TABLE; Schema: public; Owner: app
--

CREATE TABLE public.company_document (
    id integer NOT NULL,
    id_company_id integer NOT NULL,
    type character varying(255) NOT NULL,
    route character varying(255) NOT NULL
);


ALTER TABLE public.company_document OWNER TO app;

--
-- Name: company_document_id_seq; Type: SEQUENCE; Schema: public; Owner: app
--

CREATE SEQUENCE public.company_document_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.company_document_id_seq OWNER TO app;

--
-- Name: company_document_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: app
--

ALTER SEQUENCE public.company_document_id_seq OWNED BY public.company_document.id;


--
-- Name: company_id_seq; Type: SEQUENCE; Schema: public; Owner: app
--

CREATE SEQUENCE public.company_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.company_id_seq OWNER TO app;

--
-- Name: company_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: app
--

ALTER SEQUENCE public.company_id_seq OWNED BY public.company.id;


--
-- Name: container; Type: TABLE; Schema: public; Owner: app
--

CREATE TABLE public.container (
    id integer NOT NULL,
    num character varying(255) NOT NULL,
    type character varying(255) NOT NULL
);


ALTER TABLE public.container OWNER TO app;

--
-- Name: container_id_seq; Type: SEQUENCE; Schema: public; Owner: app
--

CREATE SEQUENCE public.container_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.container_id_seq OWNER TO app;

--
-- Name: container_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: app
--

ALTER SEQUENCE public.container_id_seq OWNED BY public.container.id;


--
-- Name: container_import_request; Type: TABLE; Schema: public; Owner: app
--

CREATE TABLE public.container_import_request (
    container_id integer NOT NULL,
    import_request_id integer NOT NULL
);


ALTER TABLE public.container_import_request OWNER TO app;

--
-- Name: container_yard; Type: TABLE; Schema: public; Owner: app
--

CREATE TABLE public.container_yard (
    id integer NOT NULL,
    name character varying(255) NOT NULL,
    cr character varying(10) NOT NULL
);


ALTER TABLE public.container_yard OWNER TO app;

--
-- Name: container_yard_id_seq; Type: SEQUENCE; Schema: public; Owner: app
--

CREATE SEQUENCE public.container_yard_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.container_yard_id_seq OWNER TO app;

--
-- Name: container_yard_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: app
--

ALTER SEQUENCE public.container_yard_id_seq OWNED BY public.container_yard.id;


--
-- Name: delivery; Type: TABLE; Schema: public; Owner: app
--

CREATE TABLE public.delivery (
    id integer NOT NULL,
    reference_id integer NOT NULL,
    transport_id integer NOT NULL,
    date character varying(255) NOT NULL,
    hour character varying(255) NOT NULL
);


ALTER TABLE public.delivery OWNER TO app;

--
-- Name: delivery_id_seq; Type: SEQUENCE; Schema: public; Owner: app
--

CREATE SEQUENCE public.delivery_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.delivery_id_seq OWNER TO app;

--
-- Name: delivery_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: app
--

ALTER SEQUENCE public.delivery_id_seq OWNED BY public.delivery.id;


--
-- Name: empty_return; Type: TABLE; Schema: public; Owner: app
--

CREATE TABLE public.empty_return (
    id integer NOT NULL,
    container_id integer NOT NULL,
    reference_id integer NOT NULL,
    transport_id integer NOT NULL,
    yard_id integer NOT NULL,
    type character varying(255) NOT NULL,
    date character varying(255) NOT NULL,
    eir character varying(255) NOT NULL
);


ALTER TABLE public.empty_return OWNER TO app;

--
-- Name: empty_return_id_seq; Type: SEQUENCE; Schema: public; Owner: app
--

CREATE SEQUENCE public.empty_return_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.empty_return_id_seq OWNER TO app;

--
-- Name: empty_return_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: app
--

ALTER SEQUENCE public.empty_return_id_seq OWNED BY public.empty_return.id;


--
-- Name: freight_hauler; Type: TABLE; Schema: public; Owner: app
--

CREATE TABLE public.freight_hauler (
    id integer NOT NULL,
    id_user_id integer NOT NULL,
    caat character varying(4) NOT NULL,
    company_name character varying(255) NOT NULL,
    rfc character varying(255) NOT NULL
);


ALTER TABLE public.freight_hauler OWNER TO app;

--
-- Name: freight_hauler_id_seq; Type: SEQUENCE; Schema: public; Owner: app
--

CREATE SEQUENCE public.freight_hauler_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.freight_hauler_id_seq OWNER TO app;

--
-- Name: freight_hauler_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: app
--

ALTER SEQUENCE public.freight_hauler_id_seq OWNED BY public.freight_hauler.id;


--
-- Name: import_document; Type: TABLE; Schema: public; Owner: app
--

CREATE TABLE public.import_document (
    id integer NOT NULL,
    reference_id integer NOT NULL,
    name character varying(255) DEFAULT NULL::character varying,
    route character varying(255) NOT NULL,
    type character varying(255) NOT NULL
);


ALTER TABLE public.import_document OWNER TO app;

--
-- Name: import_document_id_seq; Type: SEQUENCE; Schema: public; Owner: app
--

CREATE SEQUENCE public.import_document_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.import_document_id_seq OWNER TO app;

--
-- Name: import_document_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: app
--

ALTER SEQUENCE public.import_document_id_seq OWNED BY public.import_document.id;


--
-- Name: import_request; Type: TABLE; Schema: public; Owner: app
--

CREATE TABLE public.import_request (
    id integer NOT NULL,
    id_company_id integer NOT NULL,
    id_provider_id integer NOT NULL,
    cr_id integer NOT NULL,
    client_reference character varying(255) NOT NULL,
    agency_reference character varying(255) NOT NULL,
    import_number character varying(255) NOT NULL,
    type character varying(255) NOT NULL,
    eta character varying(255) NOT NULL,
    status character varying(255) NOT NULL,
    goods character varying(255) NOT NULL
);


ALTER TABLE public.import_request OWNER TO app;

--
-- Name: import_request_id_seq; Type: SEQUENCE; Schema: public; Owner: app
--

CREATE SEQUENCE public.import_request_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.import_request_id_seq OWNER TO app;

--
-- Name: import_request_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: app
--

ALTER SEQUENCE public.import_request_id_seq OWNED BY public.import_request.id;


--
-- Name: intern_invoice; Type: TABLE; Schema: public; Owner: app
--

CREATE TABLE public.intern_invoice (
    id integer NOT NULL,
    reference_id integer NOT NULL,
    concept character varying(255) NOT NULL,
    route character varying(255) NOT NULL
);


ALTER TABLE public.intern_invoice OWNER TO app;

--
-- Name: intern_invoice_id_seq; Type: SEQUENCE; Schema: public; Owner: app
--

CREATE SEQUENCE public.intern_invoice_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.intern_invoice_id_seq OWNER TO app;

--
-- Name: intern_invoice_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: app
--

ALTER SEQUENCE public.intern_invoice_id_seq OWNED BY public.intern_invoice.id;


--
-- Name: messenger_messages; Type: TABLE; Schema: public; Owner: app
--

CREATE TABLE public.messenger_messages (
    id bigint NOT NULL,
    body text NOT NULL,
    headers text NOT NULL,
    queue_name character varying(190) NOT NULL,
    created_at timestamp(0) without time zone NOT NULL,
    available_at timestamp(0) without time zone NOT NULL,
    delivered_at timestamp(0) without time zone DEFAULT NULL::timestamp without time zone
);


ALTER TABLE public.messenger_messages OWNER TO app;

--
-- Name: COLUMN messenger_messages.created_at; Type: COMMENT; Schema: public; Owner: app
--

COMMENT ON COLUMN public.messenger_messages.created_at IS '(DC2Type:datetime_immutable)';


--
-- Name: COLUMN messenger_messages.available_at; Type: COMMENT; Schema: public; Owner: app
--

COMMENT ON COLUMN public.messenger_messages.available_at IS '(DC2Type:datetime_immutable)';


--
-- Name: COLUMN messenger_messages.delivered_at; Type: COMMENT; Schema: public; Owner: app
--

COMMENT ON COLUMN public.messenger_messages.delivered_at IS '(DC2Type:datetime_immutable)';


--
-- Name: messenger_messages_id_seq; Type: SEQUENCE; Schema: public; Owner: app
--

CREATE SEQUENCE public.messenger_messages_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.messenger_messages_id_seq OWNER TO app;

--
-- Name: messenger_messages_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: app
--

ALTER SEQUENCE public.messenger_messages_id_seq OWNED BY public.messenger_messages.id;


--
-- Name: operation; Type: TABLE; Schema: public; Owner: app
--

CREATE TABLE public.operation (
    id integer NOT NULL,
    reference_id integer NOT NULL,
    type character varying(255) NOT NULL,
    date character varying(255) NOT NULL
);


ALTER TABLE public.operation OWNER TO app;

--
-- Name: operation_id_seq; Type: SEQUENCE; Schema: public; Owner: app
--

CREATE SEQUENCE public.operation_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.operation_id_seq OWNER TO app;

--
-- Name: operation_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: app
--

ALTER SEQUENCE public.operation_id_seq OWNED BY public.operation.id;


--
-- Name: provider; Type: TABLE; Schema: public; Owner: app
--

CREATE TABLE public.provider (
    id integer NOT NULL,
    name character varying(255) NOT NULL,
    tax_id character varying(255) NOT NULL,
    address character varying(255) NOT NULL
);


ALTER TABLE public.provider OWNER TO app;

--
-- Name: provider_id_seq; Type: SEQUENCE; Schema: public; Owner: app
--

CREATE SEQUENCE public.provider_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.provider_id_seq OWNER TO app;

--
-- Name: provider_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: app
--

ALTER SEQUENCE public.provider_id_seq OWNED BY public.provider.id;


--
-- Name: user; Type: TABLE; Schema: public; Owner: app
--

CREATE TABLE public."user" (
    id integer NOT NULL,
    email character varying(180) NOT NULL,
    roles json NOT NULL,
    password character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    last_name character varying(255) NOT NULL,
    status character varying(255) NOT NULL
);


ALTER TABLE public."user" OWNER TO app;

--
-- Name: user_id_seq; Type: SEQUENCE; Schema: public; Owner: app
--

CREATE SEQUENCE public.user_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.user_id_seq OWNER TO app;

--
-- Name: user_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: app
--

ALTER SEQUENCE public.user_id_seq OWNED BY public."user".id;


--
-- Name: associated id; Type: DEFAULT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.associated ALTER COLUMN id SET DEFAULT nextval('public.associated_id_seq'::regclass);


--
-- Name: company id; Type: DEFAULT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.company ALTER COLUMN id SET DEFAULT nextval('public.company_id_seq'::regclass);


--
-- Name: company_document id; Type: DEFAULT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.company_document ALTER COLUMN id SET DEFAULT nextval('public.company_document_id_seq'::regclass);


--
-- Name: container id; Type: DEFAULT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.container ALTER COLUMN id SET DEFAULT nextval('public.container_id_seq'::regclass);


--
-- Name: container_yard id; Type: DEFAULT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.container_yard ALTER COLUMN id SET DEFAULT nextval('public.container_yard_id_seq'::regclass);


--
-- Name: delivery id; Type: DEFAULT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.delivery ALTER COLUMN id SET DEFAULT nextval('public.delivery_id_seq'::regclass);


--
-- Name: empty_return id; Type: DEFAULT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.empty_return ALTER COLUMN id SET DEFAULT nextval('public.empty_return_id_seq'::regclass);


--
-- Name: freight_hauler id; Type: DEFAULT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.freight_hauler ALTER COLUMN id SET DEFAULT nextval('public.freight_hauler_id_seq'::regclass);


--
-- Name: import_document id; Type: DEFAULT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.import_document ALTER COLUMN id SET DEFAULT nextval('public.import_document_id_seq'::regclass);


--
-- Name: import_request id; Type: DEFAULT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.import_request ALTER COLUMN id SET DEFAULT nextval('public.import_request_id_seq'::regclass);


--
-- Name: intern_invoice id; Type: DEFAULT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.intern_invoice ALTER COLUMN id SET DEFAULT nextval('public.intern_invoice_id_seq'::regclass);


--
-- Name: messenger_messages id; Type: DEFAULT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.messenger_messages ALTER COLUMN id SET DEFAULT nextval('public.messenger_messages_id_seq'::regclass);


--
-- Name: operation id; Type: DEFAULT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.operation ALTER COLUMN id SET DEFAULT nextval('public.operation_id_seq'::regclass);


--
-- Name: provider id; Type: DEFAULT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.provider ALTER COLUMN id SET DEFAULT nextval('public.provider_id_seq'::regclass);


--
-- Name: user id; Type: DEFAULT; Schema: public; Owner: app
--

ALTER TABLE ONLY public."user" ALTER COLUMN id SET DEFAULT nextval('public.user_id_seq'::regclass);


--
-- Data for Name: associated; Type: TABLE DATA; Schema: public; Owner: app
--

COPY public.associated (id, id_client_id, id_company_id) FROM stdin;
12	35	9
13	36	10
15	36	9
16	35	11
17	35	12
18	35	13
19	35	14
20	36	11
21	37	13
22	37	15
\.


--
-- Data for Name: company; Type: TABLE DATA; Schema: public; Owner: app
--

COPY public.company (id, name, address, rfc) FROM stdin;
10	Solrac Company Inc	Mexico	qqqq
9	Carlos Company Inc	Mexico	kkk
11	Otro gato	Mexico	gato
12	Gato	Mexico	1
13	Perro	Mexico	a
14	o	Mexico	kkk
15	Jesus inc	Mexico 3	jht
\.


--
-- Data for Name: company_document; Type: TABLE DATA; Schema: public; Owner: app
--

COPY public.company_document (id, id_company_id, type, route) FROM stdin;
12	9		uploads/empresas/kkk/Comprobante-de-registro-67fc1af5068bb.pdf
13	10		uploads/empresas/qqqq/Comprobante-de-registro-CNL-67fc1b319e2cb.pdf
14	9	CSF	uploads/empresas/kkk/Comprobante-de-registro-CNL-67fc69ecbe076.pdf
15	11		uploads/empresas/gato/convocatoria-unibeca-67fc6a23c9ded.pdf
16	12		uploads/empresas/1/REPORTE-MODULO-1-Entrega-final-Estudios-sobre-la-ciudad-OK-67fc6ab791799.pdf
17	13	CSF	uploads/empresas/a/Comprobante-de-registro-67fc6b4de2499.pdf
19	13	ddd	uploads/empresas/a/Leccion-2-El-gluten-Heroe-o-villano-67fc6b4de6dee.pdf
21	14	m	uploads/empresas/kkk/Comprobante-de-registro-CNL-67fc6b899a520.pdf
18	13	e	uploads/empresas/a/REPORTE-MODULO-1-Entrega-final-Estudios-sobre-la-ciudad-OK-67fc71a98c472.pdf
22	15	Poder Notsriasl	uploads/empresas/jht/CAAU7491142-6806e103dee8d.pdf
23	15	CSF	uploads/empresas/jht/Comprobante-de-registro-6806e103df8dd.pdf
\.


--
-- Data for Name: container; Type: TABLE DATA; Schema: public; Owner: app
--

COPY public.container (id, num, type) FROM stdin;
1	ONEUXXXX	20DC
2	MNBUXXXX	40HC
3	CAIUXXXX	40DC
4	ONEUXXXXX	40RH
\.


--
-- Data for Name: container_import_request; Type: TABLE DATA; Schema: public; Owner: app
--

COPY public.container_import_request (container_id, import_request_id) FROM stdin;
1	3
2	3
3	4
4	4
\.


--
-- Data for Name: container_yard; Type: TABLE DATA; Schema: public; Owner: app
--

COPY public.container_yard (id, name, cr) FROM stdin;
2	SSA	39
\.


--
-- Data for Name: delivery; Type: TABLE DATA; Schema: public; Owner: app
--

COPY public.delivery (id, reference_id, transport_id, date, hour) FROM stdin;
\.


--
-- Data for Name: empty_return; Type: TABLE DATA; Schema: public; Owner: app
--

COPY public.empty_return (id, container_id, reference_id, transport_id, yard_id, type, date, eir) FROM stdin;
\.


--
-- Data for Name: freight_hauler; Type: TABLE DATA; Schema: public; Owner: app
--

COPY public.freight_hauler (id, id_user_id, caat, company_name, rfc) FROM stdin;
\.


--
-- Data for Name: import_document; Type: TABLE DATA; Schema: public; Owner: app
--

COPY public.import_document (id, reference_id, name, route, type) FROM stdin;
1	2	\N	uploads/empresas/kkk1234/Comprobante-de-registro-6802d866580a6.pdf	Factura
2	3	\N	uploads/empresas/gato111/Comprobante-de-registro-6802e488c8de8.pdf	Factura
3	4	\N	uploads/empresas/jhtREF1/Comprobante-de-registro-6806e187c7716.pdf	Factura
\.


--
-- Data for Name: import_request; Type: TABLE DATA; Schema: public; Owner: app
--

COPY public.import_request (id, id_company_id, id_provider_id, cr_id, client_reference, agency_reference, import_number, type, eta, status, goods) FROM stdin;
2	9	2	2	1234	Pendiente	Pendiente	lcl	2025-04-19	Pendiente	Camisas
3	11	3	2	111	Pendiente	Pendiente	container	2025-04-19	Pendiente	Camisas
4	15	4	2	REF1	Pendiente	Pendiente	container	2025-04-22	Pendiente	Camisas
\.


--
-- Data for Name: intern_invoice; Type: TABLE DATA; Schema: public; Owner: app
--

COPY public.intern_invoice (id, reference_id, concept, route) FROM stdin;
\.


--
-- Data for Name: messenger_messages; Type: TABLE DATA; Schema: public; Owner: app
--

COPY public.messenger_messages (id, body, headers, queue_name, created_at, available_at, delivered_at) FROM stdin;
\.


--
-- Data for Name: operation; Type: TABLE DATA; Schema: public; Owner: app
--

COPY public.operation (id, reference_id, type, date) FROM stdin;
\.


--
-- Data for Name: provider; Type: TABLE DATA; Schema: public; Owner: app
--

COPY public.provider (id, name, tax_id, address) FROM stdin;
2	Am Wax	12345	Mexico 2
3	East Top	12345	Mexico 3
4	CAmisas xd	CCCCCC	China
\.


--
-- Data for Name: user; Type: TABLE DATA; Schema: public; Owner: app
--

COPY public."user" (id, email, roles, password, name, last_name, status) FROM stdin;
36	cnolazco@ucol.mx	["ROLE_CLIENT"]	$2y$10$yWZzmxmOs1D3VfuuMppGE.eV5eokE10pr35Lj/u293XYBZ2mn4mAK	Solrac	Velazco	active
35	carlos.nolazco@vca.mx	["ROLE_CLIENT"]	$2y$10$E3fY157.ngEPERZ0aUL8xOiXdTK4bbUPtl.Jh.B9GfZZyymPPDVqm	Carlos	Nolazco	active
37	a@a.com	["ROLE_CLIENT"]	$2y$10$pghJVXVYdbl87NKBx4Y6eunkozp4nNY0VHYs6DiL2hRq6UIwBqHLC	Jesus	Hernandez	active
30	gerencia@ialnetwork.com	["ROLE_ADMIN"]	$2y$10$/93eh9UByxSUzOgT3FJZ9eWp7lrnmRdZcLIj2Q0HBUEr.Pclqz1eC	IAL	Network	active
\.


--
-- Name: associated_id_seq; Type: SEQUENCE SET; Schema: public; Owner: app
--

SELECT pg_catalog.setval('public.associated_id_seq', 22, true);


--
-- Name: company_document_id_seq; Type: SEQUENCE SET; Schema: public; Owner: app
--

SELECT pg_catalog.setval('public.company_document_id_seq', 23, true);


--
-- Name: company_id_seq; Type: SEQUENCE SET; Schema: public; Owner: app
--

SELECT pg_catalog.setval('public.company_id_seq', 15, true);


--
-- Name: container_id_seq; Type: SEQUENCE SET; Schema: public; Owner: app
--

SELECT pg_catalog.setval('public.container_id_seq', 4, true);


--
-- Name: container_yard_id_seq; Type: SEQUENCE SET; Schema: public; Owner: app
--

SELECT pg_catalog.setval('public.container_yard_id_seq', 2, true);


--
-- Name: delivery_id_seq; Type: SEQUENCE SET; Schema: public; Owner: app
--

SELECT pg_catalog.setval('public.delivery_id_seq', 1, false);


--
-- Name: empty_return_id_seq; Type: SEQUENCE SET; Schema: public; Owner: app
--

SELECT pg_catalog.setval('public.empty_return_id_seq', 1, false);


--
-- Name: freight_hauler_id_seq; Type: SEQUENCE SET; Schema: public; Owner: app
--

SELECT pg_catalog.setval('public.freight_hauler_id_seq', 1, false);


--
-- Name: import_document_id_seq; Type: SEQUENCE SET; Schema: public; Owner: app
--

SELECT pg_catalog.setval('public.import_document_id_seq', 3, true);


--
-- Name: import_request_id_seq; Type: SEQUENCE SET; Schema: public; Owner: app
--

SELECT pg_catalog.setval('public.import_request_id_seq', 4, true);


--
-- Name: intern_invoice_id_seq; Type: SEQUENCE SET; Schema: public; Owner: app
--

SELECT pg_catalog.setval('public.intern_invoice_id_seq', 1, false);


--
-- Name: messenger_messages_id_seq; Type: SEQUENCE SET; Schema: public; Owner: app
--

SELECT pg_catalog.setval('public.messenger_messages_id_seq', 1, false);


--
-- Name: operation_id_seq; Type: SEQUENCE SET; Schema: public; Owner: app
--

SELECT pg_catalog.setval('public.operation_id_seq', 1, false);


--
-- Name: provider_id_seq; Type: SEQUENCE SET; Schema: public; Owner: app
--

SELECT pg_catalog.setval('public.provider_id_seq', 4, true);


--
-- Name: user_id_seq; Type: SEQUENCE SET; Schema: public; Owner: app
--

SELECT pg_catalog.setval('public.user_id_seq', 37, true);


--
-- Name: associated associated_pkey; Type: CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.associated
    ADD CONSTRAINT associated_pkey PRIMARY KEY (id);


--
-- Name: company_document company_document_pkey; Type: CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.company_document
    ADD CONSTRAINT company_document_pkey PRIMARY KEY (id);


--
-- Name: company company_pkey; Type: CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.company
    ADD CONSTRAINT company_pkey PRIMARY KEY (id);


--
-- Name: container_import_request container_import_request_pkey; Type: CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.container_import_request
    ADD CONSTRAINT container_import_request_pkey PRIMARY KEY (container_id, import_request_id);


--
-- Name: container container_pkey; Type: CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.container
    ADD CONSTRAINT container_pkey PRIMARY KEY (id);


--
-- Name: container_yard container_yard_pkey; Type: CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.container_yard
    ADD CONSTRAINT container_yard_pkey PRIMARY KEY (id);


--
-- Name: delivery delivery_pkey; Type: CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.delivery
    ADD CONSTRAINT delivery_pkey PRIMARY KEY (id);


--
-- Name: empty_return empty_return_pkey; Type: CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.empty_return
    ADD CONSTRAINT empty_return_pkey PRIMARY KEY (id);


--
-- Name: freight_hauler freight_hauler_pkey; Type: CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.freight_hauler
    ADD CONSTRAINT freight_hauler_pkey PRIMARY KEY (id);


--
-- Name: import_document import_document_pkey; Type: CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.import_document
    ADD CONSTRAINT import_document_pkey PRIMARY KEY (id);


--
-- Name: import_request import_request_pkey; Type: CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.import_request
    ADD CONSTRAINT import_request_pkey PRIMARY KEY (id);


--
-- Name: intern_invoice intern_invoice_pkey; Type: CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.intern_invoice
    ADD CONSTRAINT intern_invoice_pkey PRIMARY KEY (id);


--
-- Name: messenger_messages messenger_messages_pkey; Type: CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.messenger_messages
    ADD CONSTRAINT messenger_messages_pkey PRIMARY KEY (id);


--
-- Name: operation operation_pkey; Type: CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.operation
    ADD CONSTRAINT operation_pkey PRIMARY KEY (id);


--
-- Name: provider provider_pkey; Type: CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.provider
    ADD CONSTRAINT provider_pkey PRIMARY KEY (id);


--
-- Name: user user_pkey; Type: CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public."user"
    ADD CONSTRAINT user_pkey PRIMARY KEY (id);


--
-- Name: idx_1981a66d1645dea9; Type: INDEX; Schema: public; Owner: app
--

CREATE INDEX idx_1981a66d1645dea9 ON public.operation USING btree (reference_id);


--
-- Name: idx_1c47599d80f486b6; Type: INDEX; Schema: public; Owner: app
--

CREATE INDEX idx_1c47599d80f486b6 ON public.container_import_request USING btree (import_request_id);


--
-- Name: idx_1c47599dbc21f742; Type: INDEX; Schema: public; Owner: app
--

CREATE INDEX idx_1c47599dbc21f742 ON public.container_import_request USING btree (container_id);


--
-- Name: idx_288726731241655d; Type: INDEX; Schema: public; Owner: app
--

CREATE INDEX idx_288726731241655d ON public.import_request USING btree (id_provider_id);


--
-- Name: idx_2887267332119a01; Type: INDEX; Schema: public; Owner: app
--

CREATE INDEX idx_2887267332119a01 ON public.import_request USING btree (id_company_id);


--
-- Name: idx_2887267340868eb5; Type: INDEX; Schema: public; Owner: app
--

CREATE INDEX idx_2887267340868eb5 ON public.import_request USING btree (cr_id);


--
-- Name: idx_3781ec109909c13f; Type: INDEX; Schema: public; Owner: app
--

CREATE INDEX idx_3781ec109909c13f ON public.delivery USING btree (transport_id);


--
-- Name: idx_71c6348c1645dea9; Type: INDEX; Schema: public; Owner: app
--

CREATE INDEX idx_71c6348c1645dea9 ON public.import_document USING btree (reference_id);


--
-- Name: idx_75ea56e016ba31db; Type: INDEX; Schema: public; Owner: app
--

CREATE INDEX idx_75ea56e016ba31db ON public.messenger_messages USING btree (delivered_at);


--
-- Name: idx_75ea56e0e3bd61ce; Type: INDEX; Schema: public; Owner: app
--

CREATE INDEX idx_75ea56e0e3bd61ce ON public.messenger_messages USING btree (available_at);


--
-- Name: idx_75ea56e0fb7336f0; Type: INDEX; Schema: public; Owner: app
--

CREATE INDEX idx_75ea56e0fb7336f0 ON public.messenger_messages USING btree (queue_name);


--
-- Name: idx_7bbc9c401645dea9; Type: INDEX; Schema: public; Owner: app
--

CREATE INDEX idx_7bbc9c401645dea9 ON public.empty_return USING btree (reference_id);


--
-- Name: idx_7bbc9c40896259a0; Type: INDEX; Schema: public; Owner: app
--

CREATE INDEX idx_7bbc9c40896259a0 ON public.empty_return USING btree (yard_id);


--
-- Name: idx_7bbc9c409909c13f; Type: INDEX; Schema: public; Owner: app
--

CREATE INDEX idx_7bbc9c409909c13f ON public.empty_return USING btree (transport_id);


--
-- Name: idx_c0fe9f1b32119a01; Type: INDEX; Schema: public; Owner: app
--

CREATE INDEX idx_c0fe9f1b32119a01 ON public.company_document USING btree (id_company_id);


--
-- Name: idx_d3d550d632119a01; Type: INDEX; Schema: public; Owner: app
--

CREATE INDEX idx_d3d550d632119a01 ON public.associated USING btree (id_company_id);


--
-- Name: idx_d3d550d699ded506; Type: INDEX; Schema: public; Owner: app
--

CREATE INDEX idx_d3d550d699ded506 ON public.associated USING btree (id_client_id);


--
-- Name: idx_e6f879721645dea9; Type: INDEX; Schema: public; Owner: app
--

CREATE INDEX idx_e6f879721645dea9 ON public.intern_invoice USING btree (reference_id);


--
-- Name: uniq_363bc36179f37ae5; Type: INDEX; Schema: public; Owner: app
--

CREATE UNIQUE INDEX uniq_363bc36179f37ae5 ON public.freight_hauler USING btree (id_user_id);


--
-- Name: uniq_3781ec101645dea9; Type: INDEX; Schema: public; Owner: app
--

CREATE UNIQUE INDEX uniq_3781ec101645dea9 ON public.delivery USING btree (reference_id);


--
-- Name: uniq_7bbc9c40bc21f742; Type: INDEX; Schema: public; Owner: app
--

CREATE UNIQUE INDEX uniq_7bbc9c40bc21f742 ON public.empty_return USING btree (container_id);


--
-- Name: uniq_identifier_email; Type: INDEX; Schema: public; Owner: app
--

CREATE UNIQUE INDEX uniq_identifier_email ON public."user" USING btree (email);


--
-- Name: messenger_messages notify_trigger; Type: TRIGGER; Schema: public; Owner: app
--

CREATE TRIGGER notify_trigger AFTER INSERT OR UPDATE ON public.messenger_messages FOR EACH ROW EXECUTE FUNCTION public.notify_messenger_messages();


--
-- Name: operation fk_1981a66d1645dea9; Type: FK CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.operation
    ADD CONSTRAINT fk_1981a66d1645dea9 FOREIGN KEY (reference_id) REFERENCES public.import_request(id);


--
-- Name: container_import_request fk_1c47599d80f486b6; Type: FK CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.container_import_request
    ADD CONSTRAINT fk_1c47599d80f486b6 FOREIGN KEY (import_request_id) REFERENCES public.import_request(id) ON DELETE CASCADE;


--
-- Name: container_import_request fk_1c47599dbc21f742; Type: FK CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.container_import_request
    ADD CONSTRAINT fk_1c47599dbc21f742 FOREIGN KEY (container_id) REFERENCES public.container(id) ON DELETE CASCADE;


--
-- Name: import_request fk_288726731241655d; Type: FK CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.import_request
    ADD CONSTRAINT fk_288726731241655d FOREIGN KEY (id_provider_id) REFERENCES public.provider(id);


--
-- Name: import_request fk_2887267332119a01; Type: FK CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.import_request
    ADD CONSTRAINT fk_2887267332119a01 FOREIGN KEY (id_company_id) REFERENCES public.company(id);


--
-- Name: import_request fk_2887267340868eb5; Type: FK CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.import_request
    ADD CONSTRAINT fk_2887267340868eb5 FOREIGN KEY (cr_id) REFERENCES public.container_yard(id);


--
-- Name: freight_hauler fk_363bc36179f37ae5; Type: FK CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.freight_hauler
    ADD CONSTRAINT fk_363bc36179f37ae5 FOREIGN KEY (id_user_id) REFERENCES public."user"(id);


--
-- Name: delivery fk_3781ec101645dea9; Type: FK CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.delivery
    ADD CONSTRAINT fk_3781ec101645dea9 FOREIGN KEY (reference_id) REFERENCES public.import_request(id);


--
-- Name: delivery fk_3781ec109909c13f; Type: FK CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.delivery
    ADD CONSTRAINT fk_3781ec109909c13f FOREIGN KEY (transport_id) REFERENCES public.freight_hauler(id);


--
-- Name: import_document fk_71c6348c1645dea9; Type: FK CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.import_document
    ADD CONSTRAINT fk_71c6348c1645dea9 FOREIGN KEY (reference_id) REFERENCES public.import_request(id);


--
-- Name: empty_return fk_7bbc9c401645dea9; Type: FK CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.empty_return
    ADD CONSTRAINT fk_7bbc9c401645dea9 FOREIGN KEY (reference_id) REFERENCES public.import_request(id);


--
-- Name: empty_return fk_7bbc9c40896259a0; Type: FK CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.empty_return
    ADD CONSTRAINT fk_7bbc9c40896259a0 FOREIGN KEY (yard_id) REFERENCES public.container_yard(id);


--
-- Name: empty_return fk_7bbc9c409909c13f; Type: FK CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.empty_return
    ADD CONSTRAINT fk_7bbc9c409909c13f FOREIGN KEY (transport_id) REFERENCES public.freight_hauler(id);


--
-- Name: empty_return fk_7bbc9c40bc21f742; Type: FK CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.empty_return
    ADD CONSTRAINT fk_7bbc9c40bc21f742 FOREIGN KEY (container_id) REFERENCES public.container(id);


--
-- Name: company_document fk_c0fe9f1b32119a01; Type: FK CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.company_document
    ADD CONSTRAINT fk_c0fe9f1b32119a01 FOREIGN KEY (id_company_id) REFERENCES public.company(id);


--
-- Name: associated fk_d3d550d632119a01; Type: FK CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.associated
    ADD CONSTRAINT fk_d3d550d632119a01 FOREIGN KEY (id_company_id) REFERENCES public.company(id);


--
-- Name: associated fk_d3d550d699ded506; Type: FK CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.associated
    ADD CONSTRAINT fk_d3d550d699ded506 FOREIGN KEY (id_client_id) REFERENCES public."user"(id);


--
-- Name: intern_invoice fk_e6f879721645dea9; Type: FK CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.intern_invoice
    ADD CONSTRAINT fk_e6f879721645dea9 FOREIGN KEY (reference_id) REFERENCES public.import_request(id);


--
-- PostgreSQL database dump complete
--

