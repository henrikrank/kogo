.PHONY: deploy deploy-with-db

deploy:
	bash scripts/deploy.sh theme

deploy-with-db:
	bash scripts/deploy.sh full
