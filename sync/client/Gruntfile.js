module.exports = function (grunt) {
  grunt.initConfig({
    pkg: grunt.file.readJSON('package.json'),
    config: grunt.file.readJSON('config.json'),
    // File Watching
    watch: {
      js: {
        files: [ './src/js/**/*.js' ],
        tasks: [ 'concat:js' ]
      },
      css: {
        files: [ './src/css/**/*.css' ],
        tasks: [ 'concat:css' ]
      },
      grunt: {
        files: [ 'Gruntfile.js' ]
      }
    },
    // JavaScript
    concat: {
      js: {
        options: {
          sourceMap: true,
          separator: ';\n\n'
        },
        src: [
          './src/js/app.js',
          './src/js/lib/reconnecting-websocket.js',
          './src/js/lib/mustache.js',
          './src/js/emitter.js',
          './src/js/socket.js',
          './src/js/pages/*.js',
          './src/js/components/*.js'
        ],
        dest: './build/libremail.js'
      },
      css: {
        options: {
          sourceMap: true
        },
        src: [
          './src/css/lib/normalize.css',
          './src/css/lib/skeleton.css',
          './src/css/fonts.css',
          './src/css/forms.css',
          './src/css/stage.css',
          './src/css/buttons.css',
          './src/css/dropdowns.css',
          './src/css/header.css',
          './src/css/folders.css',
          './src/css/status.css',
          './src/css/notifications.css',
          './src/css/media.css'
        ],
        dest: './build/libremail.css'
      }
    },
    copy: {
      fonts: {
        files: [{
          expand: true,
          flatten: true,
          src: [
            './src/fonts/**/*.woff'
          ],
          dest: './build/fonts/'
        }]
      },
      dist: {
        files: [{
          expand: true,
          flatten: true,
          src: [
            './build/*.js',
            './build/*.css',
            './build/*.map',
            './build/fonts'
          ],
          dest: './dist/'
        }]
      }
    },
    // HTML paths
    replace: {
      config: {
        overwrite: true,
        src: [ 'build/libremail.js' ],
        replacements: [{
          from: '%WEBSOCKET_URL%',
          to: 'ws://<%= config.server %>:<%= config.port %>/stats'
        }]
      },
      dist: {
        overwrite: true,
        src: [ 'index.html' ],
        replacements: [{
          from: 'href="build/',
          to: 'href="dist/'
        }, {
          from: 'src="build/',
          to: 'src="dist/'
        }]
      },
      build: {
        overwrite: true,
        src: [ 'index.html' ],
        replacements: [{
          from: 'href="dist/',
          to: 'href="build/'
        }, {
          from: 'src="dist/',
          to: 'src="build/'
        }]
      }
    }
  });

  grunt.loadNpmTasks('grunt-text-replace');
  grunt.loadNpmTasks('grunt-contrib-copy');
  grunt.loadNpmTasks('grunt-contrib-watch');
  grunt.loadNpmTasks('grunt-contrib-concat');

  grunt.registerTask('dist', [ 'build', 'replace:dist', 'copy:dist' ]);
  grunt.registerTask('build', [ 'concat', 'copy:fonts', 'replace:config', 'replace:build' ]);
  grunt.registerTask('dev', [ 'build', 'watch' ]);
  grunt.registerTask('default', [ 'dist' ]);
  grunt.registerTask('printenv', function () {
    console.log(process.env);
  });
};
