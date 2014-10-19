module.exports = {
    dev: {
        options: {
          beautify: true,
          mangle: false,
          compress: false
        },
        files: {
            'build/js-dev/zero-spam.js': ['src/js/zero-spam.js'],
            'build/js-dev/zero-spam-admin.js': ['src/js/zero-spam-admin.js'],
            'build/js-dev/charts.js': ['src/js/raphael.min.js', 'src/js/morris.min.js', 'src/js/jvectormap.min.js', 'src/js/jvectormap-world-mill-en.js']
        }
    },
    prod: {
        files: {
            'build/js/zero-spam.min.js': ['src/js/zero-spam.js'],
            'build/js/zero-spam-admin.min.js': ['src/js/zero-spam-admin.js'],
            'build/js/charts.min.js': ['src/js/raphael.min.js', 'src/js/morris.min.js', 'src/js/jvectormap.min.js', 'src/js/jvectormap-world-mill-en.js']
        }
    }
};
