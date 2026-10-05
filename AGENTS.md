# Instrucciones para agentes

- El agente puede desplegar al servidor sin pedir confirmación cuando termine un cambio que
  le haya pedido el propietario. Antes de desplegar, debe comprobar los cambios en local
  (sintaxis PHP con `php -l`, pruebas, servidor local si aplica).
- Para desplegar se usa siempre `manage.ps1` en modo sin preguntas, desde la raíz del repo:

  ```powershell
  powershell -NoProfile -ExecutionPolicy Bypass -File .\manage.ps1 -Action deploy -Message "Descripción del cambio"
  ```

  Hace lo mismo que la opción 1a del menú: `git add .`, commit, push a GitHub (`main`) y
  subida al servidor por SSH de los archivos cambiados desde el último despliegue.
- Antes de desplegar, `git status` debe mostrar solo los cambios del agente; si hay otros
  cambios sin commitear del propietario, preguntar antes.
- No desplegar si el propietario dice que solo quiere los cambios en local.
- Nunca tocar el servidor de otra forma (ni ssh/scp manual, ni borrar archivos, ni la base de
  datos ni `aula_data`); solo a través de `manage.ps1`.
- Si el despliegue falla, no reintentar con otros métodos: informar del error al propietario.
