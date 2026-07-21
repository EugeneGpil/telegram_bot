# make send TEXT="hello" [URL="https://..."] [CHAT_ID="..."] [AT="Y-m-d H:i"] [DRY_RUN=1]
.PHONY: send dispatch scheduled cancelled artisan build

ARGS = --text "$(TEXT)" $(if $(URL),--url "$(URL)") $(if $(CHAT_ID),--chat-id "$(CHAT_ID)") $(if $(AT),--at "$(AT)") $(if $(DRY_RUN),--dry-run)

send:
	@docker compose run --rm telegram php artisan telegram:send $(ARGS)

dispatch:
	@docker compose run --rm telegram php artisan telegram:dispatch

scheduled:
	@docker compose run --rm telegram php artisan telegram:scheduled

cancelled:
	@docker compose run --rm telegram php artisan telegram:cancelled

# make artisan CMD="migrate"
artisan:
	@docker compose run --rm telegram php artisan $(CMD)

build:
	docker compose build
