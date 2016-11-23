module.exports = {
  gruntfile: {
    files: ['Gruntfile.js'],
    tasks: ['jshint:gruntfile']
  },
  scripts: {
    files: ['src/js/**/*.js'],
    tasks: ['jshint', 'uglify']
  },
  images: {
  	files: ['src/img/**/*.jpg', '!src/img/country-flags'],
  	tasks: ['imagemin']
  }
};
