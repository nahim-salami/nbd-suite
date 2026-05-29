/* NBD Masterclass &amp; Events — frontend (filtres archive) */
(function(){
  'use strict';

  // Filtres MASTERCLASS
  document.addEventListener('click', function(e){
    if (!e.target.matches('.nbd-filter-btn')) return;
    e.preventDefault();
    var btn = e.target;
    var filter = btn.dataset.filter;
    var container = btn.closest('.nbd-masterclass');
    if (!container) return;

    container.querySelectorAll('.nbd-filter-btn').forEach(function(b){ b.classList.remove('active'); });
    btn.classList.add('active');

    container.querySelectorAll('.nbd-product-card').forEach(function(card){
      card.style.display = (filter === 'all' || card.dataset.category === filter) ? '' : 'none';
    });
  });

  // Filtres ÉVÉNEMENTS
  document.addEventListener('click', function(e){
    if (!e.target.matches('.nbd-events-filter-btn')) return;
    e.preventDefault();
    var btn = e.target;
    var filter = btn.dataset.filter;
    var container = btn.closest('.nbd-events');
    if (!container) return;

    container.querySelectorAll('.nbd-events-filter-btn').forEach(function(b){ b.classList.remove('active'); });
    btn.classList.add('active');

    container.querySelectorAll('.nbd-event-card').forEach(function(card){
      card.style.display = (filter === 'all' || card.dataset.category === filter) ? '' : 'none';
    });
  });

  // CATALOGUE — Variante B : onglets
  document.addEventListener('click', function(e){
    if (!e.target.closest('.nbd-catalog-tab')) return;
    var btn = e.target.closest('.nbd-catalog-tab');
    var tab = btn.dataset.tab;
    var container = btn.closest('.nbd-catalog');
    if (!container) return;
    container.querySelectorAll('.nbd-catalog-tab').forEach(function(b){ b.classList.remove('active'); });
    container.querySelectorAll('.nbd-catalog-tab-panel').forEach(function(p){ p.classList.remove('active'); });
    btn.classList.add('active');
    var panel = container.querySelector('#nbd-panel-' + tab);
    if (panel) panel.classList.add('active');
  });

  // CATALOGUE — Variante C/D : filtres pills
  document.addEventListener('click', function(e){
    if (!e.target.closest('.nbd-catalog-filter-btn')) return;
    var btn = e.target.closest('.nbd-catalog-filter-btn');
    var f = btn.dataset.filter;
    var container = btn.closest('.nbd-catalog, .nbd-catalog-d-wrapper');
    if (!container) return;
    container.querySelectorAll('.nbd-catalog-filter-btn').forEach(function(b){ b.classList.remove('active'); });
    btn.classList.add('active');

    // Cible : grille unifiée (D) ou toutes les cards (C)
    var scope = container.querySelector('.nbd-catalog-grid-unified') || container;
    scope.querySelectorAll('.nbd-product-card').forEach(function(card){
      card.style.display = (f === 'all' || card.dataset.type === f) ? '' : 'none';
    });
  });

  // CATALOGUE — Variante D : view switcher (sections / unified)
  document.addEventListener('click', function(e){
    if (!e.target.closest('.nbd-catalog-view-btn')) return;
    var btn = e.target.closest('.nbd-catalog-view-btn');
    var view = btn.dataset.view;
    var wrapper = btn.closest('.nbd-catalog-d-wrapper');
    if (!wrapper) return;
    wrapper.querySelectorAll('.nbd-catalog-view-btn').forEach(function(b){ b.classList.remove('active'); });
    btn.classList.add('active');
    wrapper.dataset.view = view;
  });

})();
