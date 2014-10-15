module.exports = {
    git: {
        command: [
            'git checkout master',
            'git pull origin master',
            'git fetch'
        ].join('&&')
    },
    deploy: {
        command: './deploy.sh'
    }
};
