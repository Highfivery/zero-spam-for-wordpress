module.exports = {
    gruntfile: ['Gruntfile.js'],
    src: [
    	'src/js/**/*.js',
    	'!src/js/jvectormap-world-mill-en.js',
    	'!src/js/jvectormap.min.js',
    	'!src/js/morris.min.js',
    	'!src/js/raphael.min.js'
    ]
};
