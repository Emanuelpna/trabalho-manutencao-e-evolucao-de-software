variable "docker_image_php_cli" {
  description = "Nome da Imagem Docker com o PHP CLI"
  type        = string
  default     = "php-cli"
}

variable "docker_image_php_fpm" {
  description = "Nome da Imagem Docker com o PHP FPM"
  type        = string
  default     = "php-fpm"
}

variable "docker_image_webserver" {
  description = "Nome da Imagem Docker com o Webserver"
  type        = string
  default     = "webserver"
}
