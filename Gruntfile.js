//The wrapper function
module.exports = function(grunt) {

    require('load-grunt-tasks')(grunt);

    // Project configuration & task configuration
    grunt.initConfig({

        pkg: grunt.file.readJSON('package.json'),

        // Clean up build directory
        clean: {
            main: ['build/<%= pkg.name %>']
        },

        // Copy the plugin into the build directory
        copy: {
            main: {
                src:  [
                    '**',
                    '!node_modules/**',
                    '!vendor/**',
                    '!apigen/**',
                    '!build/**',
                    '!wp-content/**',
                    '!.hg/**',
                    '!.hgcheck/**',
                    '!Gruntfile.js',
                    '!package.json',
                    '!composer.json',
                    '!composer.lock',
                    '!.hgignore',
                    '!**/*~'
                ],
                dest: 'build/<%= pkg.name %>/'
            }
        },

        // Compress build directory into <package name>.zip and <package name>-<version>.zip
        compress: {
            main: {
                options: {
                    mode: 'zip',
                    archive: './build/<%= pkg.name %>.zip'
                },
                expand: true,
                cwd: 'build/<%= pkg.name %>/',
                src: ['**/*'],
                dest: '<%= pkg.name %>/'
            },
            version: {
                options: {
                    mode: 'zip',
                    archive: './build/<%= pkg.name %>-<%= pkg.version %>.zip'
                },
                expand: true,
                cwd: 'build/<%= pkg.name %>/',
                src: ['**/*'],
                dest: '<%= pkg.name %>/'
            }
        },

        // Generate i18n POT file
        pot: {
            options:{
                text_domain: 'mp-quiz', // Plugin Text Domain. Produces text-domain.pot
                package_name: '<%= pkg.name %>',
                package_version: '<%= pkg.version %>',
                copyright_holder: '<%= pkg.author %>',
                dest: 'languages/', // Directory to place the pot file
                keywords: [ // WordPress l10n functions
                    '__:1',
                    '_e:1',
                    '_x:1,2c',
                    'esc_html__:1',
                    'esc_html_e:1',
                    'esc_html_x:1,2c',
                    'esc_attr__:1',
                    'esc_attr_e:1',
                    'esc_attr_x:1,2c',
                    '_ex:1,2c',
                    '_n:1,2',
                    '_nx:1,2,4c',
                    '_n_noop:1,2',
                    '_nx_noop:1,2,3c'
                ]
            },
            files:{
                src:  [ 'library/*.php', 'templates/*.php' ],
                expand: true
            }
        },

        // Generate MO files from PO files
        po2mo: {
            files: {
                src: 'languages/*.po',
                expand: true
            }
        },

        // Execute shell scripts
        shell: {

            // Generate docs
            makeDocs: {
                options: {
                    stdout: true
                },
                command: 'apigen --config apigen/apigen.conf'
            }

        }

    });

    grunt.registerTask( 'default', ['pot'] );

    grunt.registerTask( 'docs', ['shell:makeDocs'] );

    grunt.registerTask( 'i18n', ['pot', 'po2mo'] );

    grunt.registerTask( 'build', ['docs', 'i18n', 'clean', 'copy', 'compress'] );

}