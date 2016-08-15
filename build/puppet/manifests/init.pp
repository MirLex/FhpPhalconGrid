exec { 'apt-get update':
  path => '/usr/bin',
}

#package { ['vim','htop']:
#  ensure => present,
#}

file { '/var/www/':
  ensure => 'directory',
}

include mysql, apache, php, beanstalk