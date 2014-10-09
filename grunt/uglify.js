module.exports = {
    dev: {
        options: {
          beautify: true,
          mangle: false,
          compress: false
        },
        files: {
            'build/js-dev/zero-spam.js': ['src/js/zero-spam.js'],
            'build/js-dev/charts.js': ['src/js/raphael.min.js', 'src/js/morris.min.js']
        }
    },
    prod: {
        files: {
            'build/js/zero-spam.min.js': ['src/js/zero-spam.js'],
            'build/js/charts.min.js': ['src/js/raphael.min.js', 'src/js/morris.min.js']
        }
    }
};
