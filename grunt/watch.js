module.exports = {
    gruntfile: {
        files: ['Gruntfile.js'],
        tasks: ['jshint:gruntfile']
    },
    src: {
        files: ['zero-spam.js'],
        tasks: ['jshint:src', 'uglify']
    }
};
