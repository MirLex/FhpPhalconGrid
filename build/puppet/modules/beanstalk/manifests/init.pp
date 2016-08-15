class beanstalk {

  # Install the packages mysql-server and mysql-client
  package { ['beanstalkd']:
    ensure  => present,
    require => Exec['apt-get update'];
  }
}