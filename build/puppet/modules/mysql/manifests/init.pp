class mysql {

# Install the packages mysql-server and mysql-client
  package { ['mysql-server', 'mysql-client']:
    ensure => present,
    require => Exec['apt-get update'];
  }

# Try to run the mysql server. Before we can run the service we make sure that the package is already installed.
  service { 'mysql':
    ensure  => running,
    require => [
      Package['mysql-server'],
      Package['mysql-client'],
    ];
  }

# Copy the config file form the "files" folder to the virtual machine. Again we make sure that the package is already installed.
# After the file was copied we notify the service that there were some changes and the server has to restart
  file { '/etc/mysql/my.cnf':
    source  => 'puppet:///modules/mysql/my.cnf',
    require => Package['mysql-server'],
    notify  => Service['mysql'];
  }

# setting the mysql password over the normal bash. Require again that the service is running
  exec { 'set-mysql-password':
    unless  => 'mysqladmin -uroot -proot status',
    command => "mysqladmin -uroot password root",
    path    => ['/bin', '/usr/bin'],
    require => Service['mysql'];
  }

  exec { 'update-root-user':
    path => ["/bin", "/usr/bin"],
    command => "mysql -uroot -proot -D mysql -e \"update user SET host='%' WHERE host ='localhost';flush privileges;\"",
    require => Exec["set-mysql-password"];
  }
}