module.exports = {
    gruntfile: {
        files: ['Gruntfile.js'],
        tasks: ['jshint:gruntfile']
    },
    dev: {
        files: ['src/js/zerospam.js', 'src/js/zero-spam-admin.js'],
        tasks: ['jshint:src', 'uglify:dev']
    },
    images: {
    	files: ['src/img/**/*.jpg', '!src/img/country-flags'],
    	tasks: ['copy:dev']
    }
};
