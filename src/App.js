const Router = require("./Router");
const EventBus = require("./EventBus");

var App = {
    router: new Router(),
    eventBus: new EventBus(),
};

Backbone.history.start();
