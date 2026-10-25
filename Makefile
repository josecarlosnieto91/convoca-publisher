.PHONY: test lint

# Los scripts de composer de ESTE repo se llaman `test:phpcs`, no `phpcs`: el Makefile llamaba a
# `composer phpcs`, que aquí no existe, así que `make lint` fallaba siempre aunque el código estuviera
# limpio. Se usan los alias del composer.json, que son los que corre el CI.
test:
	composer test
lint:
	composer test:phpcs
