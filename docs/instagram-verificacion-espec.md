# Verificación de Instagram: qué se comprueba y por qué

## El problema

La verificación del canal de Facebook/Instagram preguntaba por la **cuota de publicación** de la
cuenta (`GET /{ig_id}/content_publishing_limit`). Medido contra la API real el 09/10/2026:

```
GET /{ig_id}/content_publishing_limit   → 400
{"error":{"message":"(#100) Tried accessing nonexisting field (content_publishing_limit)","type":"OAuthException","code":100}}
```

Ese campo **ya no existe** en la API. Consecuencia: la pantalla mostraba un ❌ («ese ID no responde
con este token») que **tapaba la verdad** —el token estaba perfectamente— y dejaba al usuario sin
saber si Instagram funcionaba o no.

Y había un segundo problema, escondido detrás del primero: el identificador de Instagram que había
configurado en Lugg (`31892307720360552`) **no corresponde a la cuenta**:

```
GET /31892307720360552?fields=username,name  → 400 «Object with ID ... does not exist»
```

La cuenta real, preguntándole a la **Página** por la suya vinculada:

```
GET /{page_id}?fields=instagram_business_account{username}  → 200
{"instagram_business_account":{"username":"luggcentrosocial","id":"17841470398177912"},
 "id":"608790672316516"}
```

Los permisos del token sí incluyen lo necesario (`instagram_content_publish`, `instagram_basic`,
`pages_manage_posts`: 14 concedidos).

## La solución

La comprobación pasa a preguntarle a la **Página** por su **cuenta de Instagram vinculada**. Esa
llamada es la fuente fiable de dos cosas a la vez:

- el **estado** de la vinculación (si la página no tiene cuenta, se dice, sin alarmar por un fallo
  inexistente);
- el **ID real** de la cuenta, que es lo que necesita la publicación.

Con eso la verificación avisa de lo que de verdad importa: si el ID configurado **no** es el de la
cuenta vinculada, se dice **aquí**, con el valor correcto, en vez de descubrirlo con una
publicación perdida.

### Comportamiento de la frase

| Situación | Mensaje |
|---|---|
| Página con cuenta vinculada y ID correcto | `✅ Instagram: cuenta vinculada @luggcentrosocial (17841470398177912)` |
| Página con cuenta, ID configurado distinto | `❌ Instagram: el ID configurado (X) no es el de la cuenta vinculada a la página. El correcto es Y.` |
| Página sin cuenta de Instagram | `⚠️ Instagram: la página no tiene ninguna cuenta de Instagram vinculada.` |
| Meta responde error | `❌ Instagram: Meta respondió con un error (<mensaje real>)` |
| No hay conexión | `❌ Instagram: no se pudo preguntar a Meta (<motivo>)` |

## Lo que NO cambia

- El campo de identificador sigue existiendo: no se rompe la configuración de nadie.
- No se llama a ningún endpoint de publicación ni se publica nada al verificar: son GET de lectura.
- El mensaje de éxito de Facebook no se toca.

## Aceptación

1. Verificando con el token y la página de los Lugg, la comprobación de Instagram **no produce
   ningún error de API** y nombra la cuenta vinculada.
2. Con un identificador equivocado, lo dice y da el correcto.
3. Las pruebas del repositorio siguen en verde y la verificación cubre los tres casos anteriores.
