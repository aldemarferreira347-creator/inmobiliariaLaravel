# Despliegue en InfinityFree

## Cómo ejecutar la sonda

1. Editar las constantes al inicio de `deploy/infinityfree/sonda.php`: `CLAVE` (una cadena larga y aleatoria) y las credenciales MySQL del panel (`DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`). No commitear estos valores.
2. Subir el archivo a `htdocs/sonda.php`.
3. Abrir `https://<dominio>/sonda.php?clave=<CLAVE>` en el navegador.
4. Pegar el resultado en la tabla de abajo.
5. Borrar `htdocs/sonda.php` del servidor y comprobar que la URL da 404.

## Resultado de la sonda

| clave | valor | ¿cumple? |
|---|---|---|
| php_version | pendiente | pendiente |
| extensiones_faltantes | pendiente | pendiente |
| memory_limit | pendiente | pendiente |
| upload_max_filesize | pendiente | pendiente |
| post_max_size | pendiente | pendiente |
| max_execution_time | pendiente | pendiente |
| symlink | pendiente | pendiente |
| putenv | pendiente | pendiente |
| fastcgi_finish_request | pendiente | pendiente |
| argon2id | pendiente | pendiente |
| mysql_version | pendiente | pendiente |
| motor_por_defecto | pendiente | pendiente |
| fk_soportadas | pendiente | pendiente |
| create_view | pendiente | pendiente |
| smtp_587_brevo | pendiente | pendiente |
| smtp_587_gmail | pendiente | pendiente |
| stripe_https | pendiente | pendiente |

## Criterios de aceptación (requisitos mínimos)

- Bloqueante: PHP < 8.3, o falta alguna de `pdo_mysql, mbstring, openssl, fileinfo, dom, xml, ctype, tokenizer`.
- Si falta `gd`: la redimensión de `ImagenInmuebleService::redimensionarSiExcede` debe omitirse cuando `! extension_loaded('gd')` (Tarea 14).
- Si falta `zip` o `xmlwriter`: la exportación Excel se valida en la Tarea 19.
- `motor_por_defecto` y `fk_soportadas` determinan la rama condicional de la Tarea 4; `smtp_587_*` y `stripe_https` la de la Tarea 6 (`stripe_https` con cualquier código HTTP distinto de 0, p. ej. 401 o 404, indica conectividad correcta; 0 = sin salida HTTPS).
