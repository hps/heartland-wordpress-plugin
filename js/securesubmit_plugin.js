(function(){
    tinymce.create('tinymce.plugins.securesubmit', {
    
        init : function(ed, url){
            ed.addCommand('mceilcPHP', function(){
                ilc_sel_content = tinyMCE.activeEditor.selection.getContent();
                var width = jQuery(window).width(), H = jQuery(window).height(), W = ( 720 < width ) ? 720 : width;
                W = W - 80;
                tb_show( 'SecureSubmit Button Builder', '#TB_inline?width=' + W + '&height=' + H + '&inlineId=securesubmit-form' );
            });
            ed.addButton('securesubmit', {
                title: 'SecureSubmit Button Builder',
                image: url + '/../shield.png',
                cmd: 'mceilcPHP'
            });
        },
        createControl : function(n, cm){
            return null;
        },
        getInfo : function(){
            return {
                longname: 'SecureSubmit Button Builder',
                author: '@phr0ze',
                authorurl: 'https://developer.heartlandpaymentsystems.com/',
                infourl: 'https://developer.heartlandpaymentsystems.com/',
                version: "1.0"
            };
        }
    });

    tinymce.PluginManager.add('securesubmit', tinymce.plugins.securesubmit);

    jQuery(function(){
        var scriptpath = jQuery("script[src]").last().attr("src").split('?')[0].split('/').slice(0, -5).join('/')+'/';
        scriptpath += 'wp-content/plugins/donate/template/index.html';
        
        var form = jQuery('<div id="securesubmit-form"><table id="securesubmit-table" class="form-table">\
            <tr>\
                <td><iframe style="position: relative; width: 100%;" src="' + scriptpath + '" frameborder="0" class="bbIframe" /></td>\
            </tr>\
        </table>\
        <script language="text/javascript">\
            function injectValue(contents) {\
                tinyMCE.activeEditor.execCommand("mceInsertContent", 0, contents);\
                tb_remove();\
            }\
        </script>\
        </div>');
        
        var table = form.find('table');
        form.appendTo('body').hide();

        var height = jQuery(window).height() - 284;
        jQuery('.bbIframe').css('height', height);
    });
})();