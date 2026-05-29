/* NBD Masterclass — admin scripts */
(function($){
  'use strict';

  // Enregistrer les boutons H2, H3, H4 dans le mode Texte (Quicktags)
  if (typeof QTags !== 'undefined') {
    QTags.addButton('h2', 'H2', '<h2>', '</h2>\n', '', 'Titre H2', 110);
    QTags.addButton('h3', 'H3', '<h3>', '</h3>\n', '', 'Titre H3', 111);
    QTags.addButton('h4', 'H4', '<h4>', '</h4>\n', '', 'Titre H4', 112);
  }

  $(function(){

    /* ----------- MEDIA UPLOADER ----------- */
    $(document).on('click', '.nbd-mc-upload-btn', function(e){
      e.preventDefault();
      var $btn = $(this);
      var $picker = $btn.closest('.nbd-mc-media-picker');
      var $input = $picker.find('input[type="hidden"]');
      var $preview = $picker.find('.nbd-mc-preview');

      var frame = wp.media({
        title: 'Choisir une image',
        button: { text: 'Utiliser cette image' },
        library: { type: 'image' },
        multiple: false
      });

      frame.on('select', function(){
        var att = frame.state().get('selection').first().toJSON();
        $input.val(att.id);
        $preview.html('<img src="' + att.url + '">');
      });

      frame.open();
    });

    $(document).on('click', '.nbd-mc-remove-btn', function(e){
      e.preventDefault();
      var $picker = $(this).closest('.nbd-mc-media-picker');
      $picker.find('input[type="hidden"]').val('');
      $picker.find('.nbd-mc-preview').empty();
    });

    /* ----------- REPEATER ----------- */
    $(document).on('click', '.nbd-mc-add-item', function(e){
      e.preventDefault();
      var $repeater = $(this).closest('.nbd-mc-repeater');
      var $items = $repeater.find('.nbd-mc-repeater-items');
      var $last = $items.find('.nbd-mc-repeater-item').last();
      var $new;

      if ($last.length) {
        $new = $last.clone();
        $new.find('input[type="text"], input[type="url"], textarea').val('');
        $new.find('select').each(function(){ this.selectedIndex = 0; });
      } else {
        var name = $repeater.data('name');
        var isBadges = $repeater.hasClass('nbd-mc-badges-repeater');
        var isTestim = $repeater.hasClass('nbd-mc-testimonials-repeater');
        if (isBadges) {
          $new = $('<div class="nbd-mc-repeater-item nbd-mc-badge-item">' +
            '<input type="text" name="' + name + '[__i__][icon]" placeholder="📥" style="width:50px">' +
            '<input type="text" name="' + name + '[__i__][label]" placeholder="Label">' +
            '<button type="button" class="button nbd-mc-remove-item">×</button>' +
          '</div>');
        } else if (isTestim) {
          $new = $(
            '<div class="nbd-mc-repeater-item nbd-mc-testimonial-item">' +
              '<div class="nbd-mc-field-row" style="gap:6px;margin:0 0 6px 0">' +
                '<input type="text" name="' + name + '[__i__][name]" placeholder="Nom">' +
                '<input type="text" name="' + name + '[__i__][role]" placeholder="Fonction">' +
                '<select name="' + name + '[__i__][rating]" style="width:80px">' +
                  '<option value="5">★★★★★</option>' +
                  '<option value="4">★★★★</option>' +
                  '<option value="3">★★★</option>' +
                  '<option value="2">★★</option>' +
                  '<option value="1">★</option>' +
                '</select>' +
                '<button type="button" class="button nbd-mc-remove-item">×</button>' +
              '</div>' +
              '<textarea name="' + name + '[__i__][text]" rows="3" placeholder="Témoignage..." style="width:100%"></textarea>' +
            '</div>'
          );
        } else if ($repeater.hasClass('nbd-mc-videos-repeater')) {
          $new = $(
            '<div class="nbd-mc-repeater-item nbd-mc-video-item">' +
              '<div class="nbd-mc-field" style="margin-bottom:6px">' +
                '<input type="text" name="' + name + '[__i__][title]" placeholder="Titre de la vidéo">' +
              '</div>' +
              '<div class="nbd-mc-field-row" style="gap:6px;margin:0">' +
                '<input type="url" name="' + name + '[__i__][url]" placeholder="https://www.youtube.com/watch?v=...">' +
                '<button type="button" class="button nbd-mc-remove-item">×</button>' +
              '</div>' +
            '</div>'
          );
        } else if ($repeater.hasClass('nbd-mc-modules-repeater') || $repeater.hasClass('nbd-mc-bonus-repeater')) {
          var cls = $repeater.hasClass('nbd-mc-modules-repeater') ? 'nbd-mc-module-item' : 'nbd-mc-bonus-item';
          $new = $(
            '<div class="nbd-mc-repeater-item ' + cls + '">' +
              '<div class="nbd-mc-field-row" style="gap:6px;margin:0 0 6px 0">' +
                '<input type="text" name="' + name + '[__i__][title]" placeholder="Titre">' +
                '<button type="button" class="button nbd-mc-remove-item">×</button>' +
              '</div>' +
              '<textarea name="' + name + '[__i__][description]" rows="2" placeholder="Description (optionnelle)" style="width:100%"></textarea>' +
            '</div>'
          );
        } else {
          $new = $('<div class="nbd-mc-repeater-item">' +
            '<input type="text" name="' + name + '[]">' +
            '<button type="button" class="button nbd-mc-remove-item">×</button>' +
          '</div>');
        }
      }

      $items.append($new);
      $new.find('input:first, textarea:first').first().focus();
      reindexRepeater($repeater);
    });

    $(document).on('click', '.nbd-mc-remove-item', function(e){
      e.preventDefault();
      var $repeater = $(this).closest('.nbd-mc-repeater');
      $(this).closest('.nbd-mc-repeater-item').remove();
      reindexRepeater($repeater);
    });

    function reindexRepeater($repeater){
      // Réindexe uniquement les repeaters avec index nommés (pas les array[] simples)
      var needsReindex = $repeater.hasClass('nbd-mc-badges-repeater') ||
                         $repeater.hasClass('nbd-mc-testimonials-repeater') ||
                         $repeater.hasClass('nbd-mc-videos-repeater') ||
                         $repeater.hasClass('nbd-mc-modules-repeater') ||
                         $repeater.hasClass('nbd-mc-bonus-repeater');
      if (!needsReindex) return;
      var name = $repeater.data('name');
      $repeater.find('.nbd-mc-repeater-item').each(function(i){
        $(this).find('input, textarea, select').each(function(){
          var n = $(this).attr('name');
          if (!n) return;
          $(this).attr('name', n.replace(/\[__i__\]|\[\d+\]/, '[' + i + ']'));
        });
      });
    }

    // Reindex on page load
    $('.nbd-mc-badges-repeater, .nbd-mc-testimonials-repeater, .nbd-mc-videos-repeater, .nbd-mc-modules-repeater, .nbd-mc-bonus-repeater').each(function(){ reindexRepeater($(this)); });

    /* ----------- CATÉGORIES : clic sur pill ----------- */
    $(document).on('click', '.nbd-mc-cat-pill', function(e){
      e.preventDefault();
      var slug = $(this).data('slug');
      var $input = $('#nbd-mc-category-input');
      if (!$input.length) return;
      var current = $input.val().split(',').map(function(s){ return s.trim(); }).filter(Boolean);
      if (current.indexOf(slug) === -1) {
        current.push(slug);
        $input.val(current.join(', '));
        $(this).addClass('is-added');
        setTimeout(function(){ $('.nbd-mc-cat-pill.is-added').removeClass('is-added'); }, 800);
      } else {
        // Si déjà présent, retirer
        current = current.filter(function(s){ return s !== slug; });
        $input.val(current.join(', '));
      }
    });

  });

})(jQuery);
