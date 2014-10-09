module.exports = {
    gruntfile: {
        files: ['Gruntfile.js'],
        tasks: ['jshint:gruntfile']
    },
    src: {
        files: ['src/js/zero-spam.js'],
        tasks: ['jshint:src', 'uglify']
    }
};
