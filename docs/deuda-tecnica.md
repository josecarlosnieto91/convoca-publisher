# Deuda técnica de Convoca Publisher

Registro de lo que se ha auditado y decidido, para que no vuelva a aparecer como
hallazgo ambiguo. Regla: **una acción AJAX sin ningún productor que la dispare y sin
nonce que se genere no es una API, es código muerto.**

Última revisión: 2026-09-23.

## Acciones AJAX retiradas (1.22.2)

Auditoría: se buscó el nombre literal (viejo `cp_*` y nuevo `convoca_publisher_*`) en
PHP, JS, CSS, tests, i18n y documentación de todo el ecosistema, y en el código
**desplegado** de Lugg, Biodevas y demo (plugins, mu-plugins, temas). Se siguió el
flujo productor → acción → consumidor, incluidos `data-action`, `fetch`, `$.ajax`,
generación dinámica de nombres y `wp_localize_script`.

| Acción retirada | Callback | Nonce / capacidad | Productor encontrado | Decisión y evidencia |
|---|---|---|---|---|
| `convoca_publisher_clear_log` | `Publisher::ajax_clear_log()` | nonce `convoca_publisher_clear_log` · `manage_options` | **ninguno.** El nonce no se genera en ningún sitio (`wp_create_nonce` solo existe para `share` y `preview`): ni un consumidor externo podría invocarla | **REMOVE.** La limpieza del log tiene su propio flujo, el enlace `admin.php?action=convoca_publisher_delete_log` de la página de historial (con nonce y confirmación) |
| `convoca_publisher_test_publish` | `Publisher::ajax_test_publish()` | nonce `convoca_publisher_test_publish` · `manage_options` | **ninguno** para la vía AJAX: el flujo real es el **formulario POST** de la pestaña «Probar», que usa `check_admin_referer()` y llama a `publish_test()`/`publish_post()` pintando el resultado en la página | **REMOVE.** Además de estar huérfana, la vía AJAX **se saltaba el canal de pruebas**: publicaba en las redes reales. Se conserva el flujo POST, que sí respeta la salvaguarda |
| `convoca_publisher_republish` | `Metabox::ajax_republish()` | nonce `convoca_publisher_republish` · `edit_posts` | **botón sin JS.** Existía el botón `.cp-republish` en el metabox, pero ningún JavaScript lo escuchaba (solo hay `admin.js` y `metabox.js`, y ninguno lo menciona), así que pulsarlo no hacía nada | **REMOVE** (callback, registro y el botón muerto). Para republicar está «Compartir ahora», que sí funciona |
| `convoca_publisher_dismiss_notice` | `Notifications::dismiss()` | nonce `convoca_publisher_dismiss_notice` · sin capacidad | **ninguno.** Los avisos se pintan con `is-dismissible` y `data-key`, pero nadie enviaba la petición. No hay ni un `convoca_publisher_dismiss_*` guardado en ningún sitio (Lugg, Biodevas y demo: cero) | **REMOVE.** Sin productor, sin nonce generado y sin estado persistido tras años de uso del plugin |

## Lo que se ha conservado (y por qué)

- **La lectura del meta de descarte** (`class-notifications.php`): se sigue consultando
  `convoca_publisher_dismiss_<clave>` para decidir si un aviso se muestra. Se deja a
  propósito: es el punto de reconexión si algún día se quiere descarte permanente, y
  quitarla sería un cambio mayor sin ganancia. Está comentada en el propio código.
- **`cp_test_reset()`**: ayudante del banco de pruebas (`tests/stubs.php`). No es API
  ni identificador de producto: se queda.
- **El nonce `convoca_publisher_test_publish`**: pertenece al formulario POST, que sigue
  vivo. No se toca.

## Fallo encontrado durante esta auditoría (corregido en la misma versión)

Los dos botones de la página de historial **no funcionaban**: los enlaces apuntaban a
`admin.php?action=convoca_publisher_delete_log` y `…_retry_log`, pero los handlers
estaban registrados como `admin_action_cp_delete_log` / `admin_action_cp_retry_log`.
El desajuste venía del refactor de prefijos `cp_` → `convoca_publisher_` que renombró
los enlaces y los nonces pero no los `add_action()`. Consecuencia real: «Limpiar
historial» y «Reintentar» no hacían nada (el enlace se quedaba en una acción que nadie
atendía). Corregidos los dos `add_action()`.

Lección para el cruce de verificación: además de
`admin_post_*` ↔ `admin-post.php?action=` y de AJAX ↔ JS, hay que comprobar
`admin_action_*` ↔ `admin.php?action=`. Ese par no estaba cubierto y por eso el
desajuste pasó desapercibido.

## Cómo volver a comprobarlo

```bash
# 1) que no queden registros AJAX sin productor
grep -rhoE "wp_ajax_[a-z_]+" --include='*.php' includes/ | sort -u
grep -rnoE "'action'[^,)]*[,:][^,)]*'convoca_publisher_[a-z_]+'" assets/js/*.js | sort -u

# 2) que los handlers de admin.php casen con sus enlaces
grep -rhoE "admin_action_[a-z_]+" --include='*.php' includes/ | sort -u
grep -rhoE "admin\.php\?action=[a-z_]+" --include='*.php' includes/ | sort -u
```
