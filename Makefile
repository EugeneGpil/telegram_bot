# make send TEXT="hello" [URL="https://..."] [CHAT_ID="..."] [DRY_RUN=1]
.PHONY: send build

ARGS = --text "$(TEXT)" $(if $(URL),--url "$(URL)") $(if $(CHAT_ID),--chat-id "$(CHAT_ID)") $(if $(DRY_RUN),--dry-run)

send:
	@docker compose run --rm telegram php app/send.php $(ARGS)

build:
	docker compose build
