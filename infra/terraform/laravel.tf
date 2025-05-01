terraform {
  required_providers {
    docker = {
      source  = "kreuzwerker/docker"
      version = "~> 3.0.1"
    }
  }
}

provider "docker" {}

# Config

resource "docker_network" "laravel_production_network" {
  name            = "laravel-production-network"
  check_duplicate = true
}

resource "docker_volume" "postgres_volume" {
  name = "postgres-data-production"
}

resource "docker_volume" "laravel_volume" {
  name = "laravel-storage-production"
}

# Images

resource "docker_image" "local_docker_image_php_cli" {
  name = var.docker_image_php_cli
  build {
    context    = "../../"
    dockerfile = "./infra/terraform/configfiles/php-cli/Dockerfile"
  }
}

resource "docker_image" "local_docker_image_php_fpm" {
  name = var.docker_image_php_fpm
  build {
    context    = "../../"
    target     = "production"
    dockerfile = "./infra/commons/php-fpm/Dockerfile"
  }
}

resource "docker_image" "local_docker_image_webserver" {
  name = var.docker_image_webserver
  build {
    context    = "../../"
    dockerfile = "./infra/terraform/configfiles/nginx/Dockerfile"
  }
}

resource "docker_image" "php-analyzer-redis" {
  name = "redis:alpine"
}

resource "docker_image" "php-analyzer-postgres" {
  name = "postgres:16"
}

# Containers

resource "docker_container" "php_cli" {
  image        = docker_image.local_docker_image_php_cli.image_id
  name         = "php_analyzer_php_cli"
  env          = local.envs_list
  network_mode = "bridge"
  networks_advanced {
    name = docker_network.laravel_production_network.name
  }
  tty        = true
  stdin_open = true
}

resource "docker_container" "php_fpm" {
  image        = docker_image.local_docker_image_php_fpm.image_id
  name         = "php_analyzer_php_fpm"
  env          = local.envs_list
  network_mode = "bridge"
  networks_advanced {
    name = docker_network.laravel_production_network.name
  }
  ports {
    internal = 9000
    external = 9000
  }
  volumes {
    volume_name    = docker_volume.laravel_volume.name
    container_path = "/var/www/storage"
  }
  restart    = "unless-stopped"
  depends_on = [docker_container.postgres]
  healthcheck {
    test     = ["CMD-SHELL", "php-fpm-healthcheck || exit 1"]
    interval = "10s"
    timeout  = "5s"
    retries  = 3
  }
  provisioner "local-exec" {
    command = "sleep 10"
  }
}

resource "docker_container" "webserver" {
  image        = docker_image.local_docker_image_webserver.image_id
  name         = "php_analyzer_webserver"
  network_mode = "bridge"
  networks_advanced {
    name = docker_network.laravel_production_network.name
  }
  ports {
    internal = 80
    external = 8080
  }
  volumes {
    volume_name    = docker_volume.laravel_volume.name
    container_path = "/var/www/storage"
    read_only      = true
  }
  restart = "unless-stopped"

  depends_on = [docker_container.php_fpm]
  provisioner "local-exec" {
    command = "sleep 10"
  }
}

resource "docker_container" "postgres" {
  image        = docker_image.php-analyzer-postgres.image_id
  name         = "php_analyzer_postgress"
  env          = ["POSTGRES_DB=${local.envs["DB_DATABASE"]}", "POSTGRES_USER=${local.envs["DB_USERNAME"]}", "POSTGRES_PASSWORD=${local.envs["DB_PASSWORD"]}"]
  network_mode = "bridge"
  networks_advanced {
    name = docker_network.laravel_production_network.name
  }
  ports {
    internal = 5432
    external = local.envs["DB_PORT"]
  }
  volumes {
    volume_name    = docker_volume.postgres_volume.name
    container_path = "/var/lib/postgresql/data"
  }
  healthcheck {
    test     = ["CMD", "pg_isready"]
    interval = "10s"
    timeout  = "5s"
    retries  = 5
  }
}

resource "docker_container" "redis" {
  image        = docker_image.php-analyzer-redis.image_id
  name         = "php_analyzer_redis"
  network_mode = "bridge"
  networks_advanced {
    name = docker_network.laravel_production_network.name
  }
  ports {
    internal = 80
    external = local.envs["REDIS_PORT"]
  }
}
