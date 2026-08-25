import 'bootstrap';
import "bootstrap/scss/bootstrap.scss";
import 'bootstrap-icons/font/bootstrap-icons.css';
import Swal from 'sweetalert2';
import './styles/app.css';

// Las plantillas Twig invocan Swal desde <script> en linea, asi que tiene que
// estar en el ambito global. Antes venia del <script> del CDN.
window.Swal = Swal;
