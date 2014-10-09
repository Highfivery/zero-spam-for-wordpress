module.exports = {
    gruntfile: {
        files: ['Gruntfile.js'],
        tasks: ['jshint:gruntfile']
    },
    dev: {
        files: ['src/js/zero-spam.js'],
        tasks: ['jshint:src', 'uglify:dev']
    }
};
