module.exports = function(grunt) {

  // load all grunt tasks with this command. No need to set grunt.loadNpmTasks(...) for each task separately;
  require('load-grunt-tasks')(grunt);

  // output time table
	require('time-grunt')(grunt);

  // Project configuration.
  grunt.initConfig({
    pkg: grunt.file.readJSON('package.json'),

    // ========================================================================================
    // WATCH TASK
    // ========================================================================================
    watch: {      
      php: {
        files: ['*.php'],
        tasks: ['phpcsfixer'],
        options: {
          spawn: false
        }
      }
    },

    // ========================================================================================
    // PHP CS FIXER
    // 
    // Source: https://github.com/FriendsOfPHP/PHP-CS-Fixer    //
    // Configurator: https://mlocati.github.io/php-cs-fixer-configurator/
    // ========================================================================================

    phpcsfixer: {
      app: {
          dir: ''
      },
      options: {
          bin: '../../vendor/bin/php-cs-fixer',
          configfile: '.php_cs',
          quiet: true
      }
    }
  }); // end of grunt configuration
};
