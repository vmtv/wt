(function ($, Drupal, drupalSettings) {

  "use strict";

  Drupal.behaviors.wtCommonBehavior = {
    attach: function (context, settings) {
      $(context).find('#block-searchmoviesblock').once('block').each(function() {
        var elem = $(this);

        if (elem.parents('body.path-frontpage').length > 0) {
          $(window).bind('resize', function () {
            elem.height($(this).height() - $("#header").outerHeight() - $(".site-footer").outerHeight());
          });

          $(window).trigger('resize');
        }

        elem.find('form').bind('submit', function (e) {
          $("body").append('<div class="loader loader-fullscreen"><div class="fa fa-10x fa-spinner fa-spin"></div></div>');
        });
      });

      $(context).find('#movies-load-more').once('more').each(function() {
        $(this).bind('click', function (e) {
          e.preventDefault();

          var elem = $(this);
          var wrapper = elem.parent();
          var search = window.location.search;
          search += ((!(search.indexOf('?') >= 0)) ? '?' : '') + 'page=' + elem.data('page');

          wrapper.html('<div class="loader"><div class="fa fa-5x fa-spinner fa-spin"></div></div>');

          $.ajax({
            url: window.location.pathname + search,
            type: 'POST',
            dataType: 'JSON'
          }).always(function(data) {
            if (data.responseText) {
              wrapper.replaceWith(data.responseText);
              Drupal.behaviors.wtCommonBehavior.attach('.movies-list');
            }
            else {
              wrapper.html('<div class="alert alert-danger">' + Drupal.t('Ooops! Something went wrong.') + '</div>');
            }
          });

          return false;
        })
      });
    }
  };

})(jQuery, Drupal, drupalSettings);
