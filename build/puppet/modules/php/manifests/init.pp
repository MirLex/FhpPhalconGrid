class php {
# Install the packages
  package { ['php5','php5-mysql','php5-gd','libapache2-mod-php5','php5-xdebug','phpunit']:
    ensure  => present,
    require => Package['apache2'],
    notify  => Service['apache2'],
  }

# Copy the files from our local machine to the virtual machine and make sure the package php5 is already installed
  file {

    '/etc/php5/apache2/conf.d/30-phalcon.ini':
      source  => 'puppet:///modules/php/30-phalcon.ini',
      require => Package['php5'];

    '/etc/php5/cli/conf.d/30-phalcon.ini':
      source  => 'puppet:///modules/php/30-phalcon.ini',
      require => Package['php5'];

    '/etc/php5/phalcon.so':
      source  => 'puppet:///modules/php/phalcon.so',
      require => Package['php5'];

    '/etc/php5':
      ensure => directory,
      before => File ['/etc/php5/phpunit-4.6.0.phar'];

    '/etc/php5/phpunit-4.6.0.phar':
      source  => 'puppet:///modules/php/phpunit-4.6.0.phar',
      require => Package['php5'];

    '/etc/php5/apache2':
      ensure => directory,
      before => File ['/etc/php5/apache2/php.ini'];

    '/etc/php5/apache2/php.ini':
      source  => 'puppet:///modules/php/apache-php.ini',
      require => Package['php5'];

    '/etc/php5/mods-available':
      ensure => directory,
      before => File ['/etc/php5/mods-available/xdebug.ini'];

    '/etc/php5/mods-available/xdebug.ini':
      source  => 'puppet:///modules/php/xdebug.ini',
      require => Package['php5'];
  }
}