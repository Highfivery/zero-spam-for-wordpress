module.exports = {
    dev: {
        options: {
          beautify: true,
          mangle: false,
          compress: false
        },
        files: {
            'js/zerospam.js': ['src/js/zerospam.js'],
            'js/zero-spam-admin.js': ['src/js/zero-spam-admin.js'],
            'js/charts.js': ['src/js/raphael.min.js', 'src/js/morris.min.js', 'src/js/jvectormap.min.js', 'src/js/jvectormap-world-mill-en.js']
        }
    },
    prod: {
        files: {
            'js/zerospam.js': ['src/js/zerospam.js'],
            'js/zero-spam-admin.js': ['src/js/zero-spam-admin.js'],
            'js/charts.js': ['src/js/raphael.min.js', 'src/js/morris.min.js', 'src/js/jvectormap.min.js', 'src/js/jvectormap-world-mill-en.js']
        }
    }
};
