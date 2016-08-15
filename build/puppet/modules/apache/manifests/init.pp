class apache {

# Install the apache2 package and before we do that, make sure to run apt-get update.
  package { ['apache2']:
    ensure  => present,
    require => Exec['apt-get update'],
  }

# make sure that the apache2 service is running. Before we do that, we have to install the apache2 package
  service { 'apache2':
    ensure  => running,
    require => Package['apache2'],
  }


# This is a function to copy the files to the server. For sure you can also use the hardcoded file function for it, but this would mean a lot of copy and paste.
  apache::conf { ['sites-available/000-default.conf']: }
  apache::loadmodule{ "rewrite": }
}

# helper function for coping the files
define apache::conf() {
  file { "/etc/apache2/${name}":
    source  => "puppet:///modules/apache/${name}",
    require => Package['apache2'],
    notify  => Service['apache2'];
  }
}

# load apache modules
define apache::loadmodule () {
  exec { "/usr/sbin/a2enmod $name" :
    unless  => "/bin/readlink -e /etc/apache2/mods-enabled/${name}.load",
    require => Package['apache2'],
    notify  => Service[apache2]
  }
}