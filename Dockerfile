FROM php:8.2-cli

RUN docker-php-ext-install sockets


COPY . /usr/src/flarm

WORKDIR /usr/src/flarm
CMD [ "php", "./run-flarm.php" ]

# open docker desktop run on terminal
# docker login -u 301167  docker.io
# docker build . -t 301167/flarm:latest
# docker push 301167/flarm:latest