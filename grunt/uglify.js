module.exports = {
  prod: {
    options: {
      compress: false,
      mangle: false,
      beautify: true
    },
    files: {
      'js/zerospam.js': ['src/js/zerospam.js'],
      'js/zero-spam-admin.js': ['src/js/zero-spam-admin.js'],
      'js/charts.js': [
        'src/js/lib/raphael.min.js',
        'src/js/lib/morris.min.js',
        'src/js/lib/jvectormap.min.js',
        'src/js/lib/jvectormap-world-mill-en.js'
      ]
    }
  }
};
