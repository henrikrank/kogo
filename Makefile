.PHONY: deploy deploy-with-db snapshot-local snapshot-remote apply-snapshot-local apply-snapshot-remote

deploy:
	bash scripts/deploy.sh theme

deploy-with-db:
	bash scripts/deploy.sh full

snapshot-local:
	bash scripts/snapshot.sh snapshot local

snapshot-remote:
	bash scripts/snapshot.sh snapshot remote

apply-snapshot-local:
	bash scripts/snapshot.sh apply local

apply-snapshot-remote:
	bash scripts/snapshot.sh apply remote
