module.exports = {
    git: {
        command: [
            'git checkout master',
            'git pull origin master'
        ].join('&&')
    },
    deploy: {
        command: './deploy.sh'
    }
};
