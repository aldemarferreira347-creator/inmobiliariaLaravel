import { iniciarInterfaz } from './ui';
import { iniciarAvisos } from './avisos';
import { iniciarChat } from './chat';
import { iniciarCitas } from './citas';
import { iniciarContratos } from './contratos';
import { iniciarCuentaAtras } from './cuenta-atras';
import { iniciarGaleria } from './galeria';
import { iniciarEstadoInmueble } from './estado-inmueble';
import { iniciarFiltroNotificaciones } from './notificaciones';
import { iniciarPerfil } from './perfil';
import { iniciarTablas } from './tabla';
import { iniciarTarjetas } from './tarjetas';

document.addEventListener('DOMContentLoaded', () => {
    iniciarInterfaz();
    iniciarAvisos();
    iniciarChat();
    iniciarCitas();
    iniciarContratos();
    iniciarCuentaAtras();
    iniciarGaleria();
    iniciarEstadoInmueble();
    iniciarFiltroNotificaciones();
    iniciarPerfil();
    iniciarTablas();
    iniciarTarjetas();
});
