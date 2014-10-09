module.exports = {
    dev: {
        options: {
            config: 'config.rb',
            watch: true
        }
    },
    prod: {
        options: {
            config: 'config.rb',
            environment: 'production'
        }
    }
};
