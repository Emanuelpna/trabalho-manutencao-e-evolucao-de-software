locals {
  envs      = { for tuple in regexall("(.*)=(.*)", file("./../../.env")) : tuple[0] => tuple[1] }
  envs_list = flatten([for env, env_value in local.envs : "${env}=${env_value}"])
}
