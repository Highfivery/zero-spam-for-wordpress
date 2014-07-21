module.exports = {
    gruntfile: {
        files: ['Gruntfile.js'],
        tasks: ['jshint:gruntfile']
    },
    src: {
        files: ['zero-spam.js'],
        tasks: ['jshint:src', 'uglify']
    },
    trunk: {
        files: ['zero-spam.js', 'zero-spam.min.js', 'zero-spam.php', 'readme.txt'],
        tasks: ['copy']
    }
};
