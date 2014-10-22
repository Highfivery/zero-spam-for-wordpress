module.exports = {
    git: {
        command: [
            'git checkout master',
            'git pull origin master',
            'git fetch'
        ].join('&&')
    },
    clean: {
    	command: [
    		'rm -rf build/js-dev/*',
    		'rm -rf build/css-dev/*',
    		'rm -rf build/img-dev/*',
    		'rm -rf build/js/*',
    		'rm -rf build/css/*',
    		'rm -rf build/img/*'
    	].join('&&')
    },
    deploy: {
        command: './deploy.sh'
    },
};
