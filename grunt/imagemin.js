module.exports = {
  imagemin: {
    files: [{
      expand: true,
      cwd: 'src/img/',
      src: ['**/*.{png,jpg,gif,svg}', '!country-flags/*'],
      dest: 'img/'
    }]
  }
};