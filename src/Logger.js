var Logger = {
       DEBUG: 100,
        INFO: 200,
        WARN: 300,
       ERROR: 400,
    CRITICAL: 500,
    debug: function (name, payload){
        this.log(this.DEBUG, name, payload);
    },
    info: function (name, payload){
        this.log(this.INFO, name, payload);
    },
    warn: function (name, payload){
        this.log(this.WARN, name, payload);
    },
    error: function (name, payload){
        this.log(this.ERROR, name, payload);
    },
    critical: function (name, payload){
        this.log(this.CRITICAL, name, payload);
    },
    log: function (level, name, payload){
        $.ajax({
            url: "/playground/vc-flowjs/www/log.php",
            contentType: "application/json; charset=utf-8",
            type: "POST",
            data: JSON.stringify({
                level: level,
                name: name,
                payload: payload,
            }),
        }).fail(function(){
            console.error(arguments);
        });
    },
};

module.exports = Logger;
