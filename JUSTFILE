set dotenv-load := false

# [DDEV] Lint files
@lint:
	./vendor/bin/ecs check --fix
	PHP_CS_FIXER_IGNORE_ENV=true ./vendor/bin/php-cs-fixer fix
	./vendor/bin/rector process

# [DDEV] Run unit and integration tests
@test:
	echo "Running unit and integration tests"; \
	vendor/bin/phpunit

# [DDEV] Run tests and create code-coverage report
@coverage:
	echo "Running unit and integration tests"; \
	echo "Once completed, the generated code coverage report can be found under ./reports)"; \
	ddev xdebug;\
	ddev exec XDEBUG_MODE=coverage vendor/bin/phpunit --coverage-html reports/;\
	ddev xdebug off
	xdg-open reports/index.html
