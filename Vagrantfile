# -*- mode: ruby -*-
# vi: set ft=ruby :

Vagrant.configure(2) do |config|
  config.vm.box = "patrickascher/vagrant-debian-jessie"
  config.vm.box_url = "https://atlas.hashicorp.com/patrickascher/vagrant-debian-jessie"

  config.ssh.username = "vagrant"
  config.ssh.password = "vagrant"

  config.vm.network "forwarded_port", guest: 80, host: 8080
  config.vm.network "forwarded_port", guest: 3306, host: 3306
  config.vm.network "forwarded_port", guest: 11300, host: 11300

  config.vm.network "private_network", ip: "192.168.33.10"
  config.vm.synced_folder "./", "/vagrant_data", id: "vagrant-root",
    owner: "root",
    group: "www-data",
    mount_options: ["dmode=775,fmode=664"]

  config.vm.provider "virtualbox" do |vb|
    vb.gui = true
    vb.memory = "1024"
   end

  config.vm.provision :puppet do |puppet|
    puppet.manifests_path = 'build/puppet/manifests'
    puppet.module_path = 'build/puppet/modules'
    puppet.manifest_file = 'init.pp'
    puppet.options = "--verbose --debug"
  end

end
