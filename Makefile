.PHONY: start bash restart down ps network

CONTAINER_NAME = php-fpm

start:
	sudo docker-compose build && sudo docker-compose up -d

bash:
	sudo docker-compose exec $(CONTAINER_NAME) bash

restart:
	sudo docker-compose down && sudo docker-compose build && sudo docker-compose up -d --remove-orphans

down:
	sudo docker-compose down

ps:
	sudo docker-compose ps
