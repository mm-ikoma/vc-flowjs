require("./IndexView.scss");
const Logger = require("../Logger");
const Flow = require("@flowjs/flow.js");
const OrgGetParams = Flow.FlowChunk.prototype.getParams;

/**
* Get params for a request
* @function
*/
Flow.FlowChunk.prototype.getParams = function() {
    var params = _.extend({
        flowChunkFingerPrint: this.fingerPrint
    }, OrgGetParams.call(this, arguments));
    return params;
};

/**
 * Finish read state
 * @function
 */
Flow.FlowChunk.prototype.readFinished = function(bytes) {
    // org {
    var me = this;
    me.readState = 2;
    me.bytes = bytes;
    // }
    var flagment = me.bytes.slice(me.bytes.size - 32);
    var reader = new FileReader();
    reader.onload = function(evt) {
        var arr = Array.prototype.slice.call(new Uint8Array(evt.target.result));
        me.fingerPrint = arr.reduce(function(prev, cur, index, array) {
            return prev + ("0" + cur.toString(16)).substr(-2);
        }, "");
        me.send();
    };
    reader.readAsArrayBuffer(flagment);
};

var IndexView = Backbone.View.extend({

    el: "body",
    template: require("./IndexView.html"),

    initialize: function() {

        var me = this;


        this.$flow = new Flow({
            target: '/playground/vc-flowjs/www/upload.php',
            chunkSize: 1024 * 1024 * 2, // 2MB
            forceChunkSize: true, // Force all chunks to be less or equal than chunkSize.
            maxChunkRetries: 5,
            chunkRetryInterval: 5000,
            generateUniqueIdentifier: function(file) {
                // Some confusion in different versions of Firefox
                var relativePath = file.relativePath || file.webkitRelativePath || file.fileName || file.name;
                return file.size + '-' + file.lastModified + "-" + relativePath.replace(/[^0-9a-zA-Z_-]/img, '');
            }
        });

        if (!this.$flow.support) {
            alert("Not supperted.");
            return;
        }

        this.$flow.on('filesSubmitted', function(files) {
            me.$el.find(".btn").prop("disabled", false);
        });

        this.$flow.on('fileProgress', function(file, chunk) {
            var progress = me.$flow.progress();
            var percent = Math.round(progress * 100) + "%";
            me.$progress.css({width: percent, padding: "1rem"});
            me.$progress.text(percent);
            if (progress == 1) {
                me.$progress.addClass("complete");
            }
        });

        this.$flow.on("error", function(message, file, chunk) {
            console.log("error", message);
        });

        this.$flow.on('complete', function() {

            var file = this.files[0];
            var fileId = encodeURIComponent(file.uniqueIdentifier);
            var fileName = encodeURIComponent(file.name);

            var evtSource = new EventSource("/playground/vc-flowjs/www/sse.php?flowIdentifier=" + fileId + "&flowFilename=" + fileName);

            evtSource.addEventListener("message", function(e) {
                Logger.info("EventSource.message", arguments);
            }, false);

            evtSource.addEventListener("ping", function(e) {
                Logger.info("EventSource.ping", arguments);
            }, false);

            evtSource.addEventListener("done", function(e) {
                Logger.info("EventSource.done", arguments);
                evtSource.close();
            }, false);

            evtSource.addEventListener("fail", function(e) {
                Logger.error("EventSource.fail", arguments);
                evtSource.close();
            }, false);

            evtSource.addEventListener("error", function(e) {
                Logger.error("EventSource.error", arguments);
                evtSource.close();
            }, false);

        });

        this.delegateEvents({
            "change  .file": this.onFileChange,
            "click .upload": this.onUpload,
            "click .cancel": this.onCancel,
            "click .resume": this.onResume
        });

        // Set name of hidden property and visibility change event
        // since some browsers only offer vendor-prefixed support
        var hidden, state, visibilityChange;
        if (typeof document.hidden !== "undefined") {
        	hidden = "hidden";
            state = "visibilityState";
        	visibilityChange = "visibilitychange";
        } else if (typeof document.mozHidden !== "undefined") {
        	hidden = "mozHidden";
            state = "mozVisibilityState";
        	visibilityChange = "mozvisibilitychange";
        } else if (typeof document.msHidden !== "undefined") {
        	hidden = "msHidden";
            state = "msVisibilityState";
        	visibilityChange = "msvisibilitychange";
        } else if (typeof document.webkitHidden !== "undefined") {
        	hidden = "webkitHidden";
            state = "webkitVisibilityState";
        	visibilityChange = "webkitvisibilitychange";
        }

        document.addEventListener(visibilityChange, function() {
            if (me.$flow.files) {
                var flowFile = me.$flow.files[0];
                Logger.debug("visibilityChange", {
                    "event": document[state],
                    "payload": me._toPayload(flowFile),
                });
                if (document[state] === "visible") {
                    if (!flowFile.isComplete()
                    && (!flowFile.isUploading() || flowFile.error)
                    && window.confirm("アップロードが中断されたか、エラーが発生しました。\nアップロードを再開しますか？")) {
                        me.$flow.resume();
                    }
                }
            }
        }, false);

    },

    render: function() {
        var me = this;
        var content = this.template();
        this.$el.html(content);
        this.$progress = this.$el.find(".progress");
        return this;
    },

    onFileChange: function(evt) {
        var file = evt.currentTarget.files[0];
        this.$flow.addFile(file);
        // プレビュー
        var video = document.querySelector(".video");
        if (video.canPlayType(file.type)) {
            video.src = window.URL.createObjectURL(file);
            video.load();
            // video.play();
        } else {
            console.warn(file.type + " cant preview.");
        }
    },

    onUpload: function() {
        this.$flow.upload();
    },

    onCancel: function() {
        this.$flow.cancel();
    },

    onResume: function() {
        this.$flow.resume();
    },

    _toPayload: function(arg){
        if (arg instanceof Flow.FlowFile) {
            return {
                "name": arg.name,
                "chunks": _.countBy(arg.chunks, function(chunk){
                    return chunk.status();
                }),
            };
        } else {
            console.log(typeof(arg));
        }
    },

});

module.exports = IndexView;
