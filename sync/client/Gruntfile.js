module.exports = function ( grunt ) {
    grunt.initConfig({
        pkg: grunt.file.readJSON( 'package.json' ),
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
                options : {
                    sourceMap: true,
                    separator: ';\n'
                },
                src: [
                    './src/js/app.js',
                    './vendor/reconnectingWebsocket/reconnecting-websocket.js',
                    './vendor/mustache.js/mustache.js',
                    './src/js/emitter.js',
                    './src/js/socket.js',
                    './src/js/pages/*.js',
                    './src/js/components/*.js',
                ],
                dest: './build/libremail.js'
            },
            css: {
                options : {
                    sourceMap: true
                },
                src: [
                    './vendor/skeleton/css/normalize.css',
                    './vendor/skeleton/css/skeleton.css',
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
            }
        }
    });

    grunt.loadNpmTasks( 'grunt-contrib-copy' );
    grunt.loadNpmTasks( 'grunt-contrib-watch' );
    grunt.loadNpmTasks( 'grunt-contrib-concat' );

    grunt.registerTask( 'build', [ 'concat', 'copy' ] );
    grunt.registerTask( 'default', [ 'concat', 'copy', 'watch' ] );
    grunt.registerTask( 'printenv', function () {
        console.log( process.env );
    });
}