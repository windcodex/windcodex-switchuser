/* global jQuery, switchuserAdmin, switchuserData */
(function ($) {
	'use strict';

	var hasAdminConfig = (typeof switchuserAdmin !== 'undefined' && switchuserAdmin);
	var toolbarConfig = (typeof switchuserData !== 'undefined' && switchuserData) ? switchuserData : ((typeof switchuserAdmin !== 'undefined' && switchuserAdmin) ? switchuserAdmin : null);
	var hasToolbarConfig = !!toolbarConfig;

	var $form = $('#sg-settings-form');
	if ($form.length && hasAdminConfig) {
		var $saveBtn = $('#sg-save-btn');
		var $resetBtn = $('#sg-reset-btn');
		var $toast = $('#sg-toast');
		var $breadcrumb = $('#sg-breadcrumb-current');
		var $footerBar = $('#sg-footer-bar');
		var toastTimer = null;

		function setBusy(busy) {
			$saveBtn.prop('disabled', !!busy);
			$resetBtn.prop('disabled', !!busy);
		}

		function showToast(message, type) {
			if (!$toast.length) {
				return;
			}
			clearTimeout(toastTimer);
			$toast
				.removeClass('sg-error sg-success sg-saving sg-reset sg-toast-show')
				.addClass(type ? ('sg-' + type) : '')
				.text(message || '');
			if (message) {
				$toast.addClass('sg-toast-show');
			}
			if (type && type !== 'saving') {
				toastTimer = setTimeout(function () {
					$toast.text('').removeClass('sg-error sg-success sg-saving sg-reset sg-toast-show');
				}, 3000);
			}
		}

		function hideToast() {
			if (!$toast.length) {
				return;
			}
			clearTimeout(toastTimer);
			$toast.text('').removeClass('sg-error sg-success sg-saving sg-reset sg-toast-show');
		}

		function updateTabUi(target, label) {
			if ($breadcrumb.length) {
				$breadcrumb.text(label || '');
			}
			if ($footerBar.length) {
				var visible = ($footerBar.data('tab-visible') || '').toString().split(',');
				$footerBar.toggle(visible.indexOf(target) !== -1);
			}
		}

		$('.sg-tab-btn').on('click', function () {
			var target = $(this).data('tab');
			var label = $(this).data('breadcrumb') || $(this).text();
			$('.sg-tab-btn').removeClass('sg-tab-active').attr('aria-selected', 'false');
			$(this).addClass('sg-tab-active').attr('aria-selected', 'true');
			$('.sg-tab-panel').removeClass('sg-tab-panel-active');
			$('.sg-tab-panel[data-panel="' + target + '"]').addClass('sg-tab-panel-active');
			updateTabUi(target, label);
		});

		var $activeTab = $('.sg-tab-btn.sg-tab-active').first();
		if ($activeTab.length) {
			updateTabUi($activeTab.data('tab'), $activeTab.data('breadcrumb') || $activeTab.text());
		}

		function collect() {
			var data = {};
			$.each($form.serializeArray(), function (_, field) {
				var match = field.name.match(/switchuser_settings\[(.+)]/);
				if (!match) {
					return;
				}
				var key = match[1];
				data[key] = field.value;
			});

			$form.find('.sg-toggle-checkbox[name^="switchuser_settings["]').each(function () {
				var name = $(this).attr('name') || '';
				var match = name.match(/switchuser_settings\[(.+)]/);
				if (!match) {
					return;
				}
				var key = match[1];
				if (!Object.prototype.hasOwnProperty.call(data, key)) {
					data[key] = 'no';
				}
			});
			return data;
		}

		function applySettingsToForm(settings) {
			if (!settings) {
				return;
			}
			$.each(settings, function (key, value) {
				var $field = $form.find('[name="switchuser_settings[' + key + ']"]');
				if (!$field.length) {
					return;
				}

				if ($field.is(':checkbox')) {
					$field.prop('checked', value === 'yes');
					return;
				}

				$field.val(value);

				// If this hidden field drives a role-grant grid, sync the checkboxes too.
				if ($field.is('[type="hidden"]') && $field.attr('id')) {
					var gridId = $field.attr('id').replace('-value', '-grid');
					var $grid = $('#' + gridId);
					if ($grid.length) {
						var roles = typeof value === 'string' ? value.split(',').filter(Boolean) : [];
						$grid.find('.sg-role-grant-cb').each(function () {
							$(this).prop('checked', roles.indexOf($(this).val()) !== -1);
						});
					}
				}

			});
		}

		// Role-grant checkboxes → keep hidden input in sync.
		$form.on('change', '.sg-role-grant-cb', function () {
			var $grid = $(this).closest('[id$="-grid"]');
			var hiddenId = $grid.attr('id').replace('-grid', '-value');
			var values = [];
			$grid.find('.sg-role-grant-cb:checked').each(function () {
				values.push($(this).val());
			});
			$('#' + hiddenId).val(values.join(','));
		});

		$saveBtn.on('click', function () {
			setBusy(true);
			hideToast();
			$.post(switchuserAdmin.ajaxUrl, {
				action: 'switchuser_save_settings',
				nonce: switchuserAdmin.saveNonce,
				settings: collect()
			}).done(function (res) {
				if (res && res.success) {
					if (res.data && res.data.settings) {
						applySettingsToForm(res.data.settings);
					}
					showToast(switchuserAdmin.i18n.saved || 'Settings saved!', 'success');
				} else {
					showToast(switchuserAdmin.i18n.saveError || 'Could not save. Please try again.', 'error');
				}
			}).fail(function () {
				showToast(switchuserAdmin.i18n.saveError || 'Could not save. Please try again.', 'error');
			}).always(function () {
				setBusy(false);
			});
		});

		$resetBtn.on('click', function () {
			if (!window.confirm(switchuserAdmin.i18n.confirmReset || 'Reset all settings to their default values? This cannot be undone.')) {
				return;
			}
			setBusy(true);
			hideToast();
			$.post(switchuserAdmin.ajaxUrl, {
				action: 'switchuser_reset_settings',
				nonce: switchuserAdmin.resetNonce
			}).done(function (res) {
				if (res && res.success && res.data && res.data.settings) {
					applySettingsToForm(res.data.settings);
					showToast(switchuserAdmin.i18n.resetDone || 'Settings reset to defaults!', 'reset');
				} else {
					showToast(switchuserAdmin.i18n.resetError || 'Could not reset. Please try again.', 'error');
				}
			}).fail(function () {
				showToast(switchuserAdmin.i18n.resetError || 'Could not reset. Please try again.', 'error');
			}).always(function () {
				setBusy(false);
			});
		});
	}

	function ensureAdminQuickSearchPopover() {
		var $existing = $('#switchuser-admin-search-popover');
		if ($existing.length) {
			return $existing;
		}
		var html = '' +
			'<div id="switchuser-admin-search-popover" class="switchuser-admin-search-popover">' +
				'<div class="switchuser-front-search switchuser-adminbar-search">' +
					'<input type="text" class="switchuser-quick-search-input" placeholder="Search user..." />' +
					'<div class="switchuser-quick-search-results" aria-live="polite"></div>' +
				'</div>' +
			'</div>';
		$('body').append(html);
		return $('#switchuser-admin-search-popover');
	}

	function positionAdminQuickSearchPopover($popover) {
		var $trigger = $('#wp-admin-bar-switchuser-search > .ab-item');
		if (!$trigger.length || !$popover.length) {
			return;
		}
		var offset = $trigger.offset();
		if (!offset) {
			return;
		}
		var wasHidden = !$popover.is(':visible');
		if (wasHidden) {
			$popover.css({ display: 'block', visibility: 'hidden' });
		}
		var popoverWidth = $popover.outerWidth() || 360;
		var popoverHeight = $popover.outerHeight() || 320;
		if (wasHidden) {
			$popover.css({ display: '', visibility: '' });
		}
		var viewportWidth = $(window).width() || popoverWidth;
		var viewportHeight = $(window).height() || popoverHeight;
		var scrollTop = $(window).scrollTop() || 0;
		var triggerTop = offset.top - scrollTop;
		var triggerBottom = triggerTop + $trigger.outerHeight();
		var left = offset.left - (window.pageXOffset || document.documentElement.scrollLeft || 0);
		if (left + popoverWidth > viewportWidth - 16) {
			left = Math.max(16, viewportWidth - popoverWidth - 16);
		}
		if (left < 16) {
			left = 16;
		}
		var spaceBelow = viewportHeight - triggerBottom;
		var spaceAbove = triggerTop;
		var openUp = spaceBelow < popoverHeight && spaceAbove > spaceBelow;
		var top = openUp ? (triggerTop - popoverHeight + 1) : (triggerBottom - 1);
		if (top < 16) {
			top = 16;
		}
		if (!openUp && top + popoverHeight > viewportHeight - 16) {
			top = Math.max(16, viewportHeight - popoverHeight - 16);
		}
		$popover.css({
			top: top + 'px',
			left: left + 'px'
		});
	}

	function closeAdminQuickSearchPopover() {
		$('#switchuser-admin-search-popover').removeClass('is-open');
		$('#wp-admin-bar-switchuser-search > .ab-item').attr('aria-expanded', 'false');
	}

	$(document)
		.off('click.switchuserQuickSearch', '#wp-admin-bar-switchuser-search > .ab-item')
		.on('click.switchuserQuickSearch', '#wp-admin-bar-switchuser-search > .ab-item', function (e) {
		e.preventDefault();
		e.stopPropagation();
		var $popover = ensureAdminQuickSearchPopover();
		positionAdminQuickSearchPopover($popover);
		if ($popover.hasClass('is-open')) {
			$popover.removeClass('is-open');
			$(this).attr('aria-expanded', 'false');
		} else {
			$popover.addClass('is-open');
			$(this).attr('aria-expanded', 'true');
			$popover.find('.switchuser-quick-search-input').trigger('focus');
		}
	});

	var searchTimer = null;

	function escapeHtml(value) {
		return $('<div/>').text(value || '').html();
	}

	function formatQuickItemLabel(item) {
		if (item && item.name && item.email && item.role) {
			return item.name + ' (' + item.email + ') (' + item.role + ')';
		}
		return (item && item.label) ? item.label : '';
	}

	function buildQuickItemHtml(item) {
		return '<a class="switchuser-quick-item"'
			+ ' href="' + escapeHtml(item.url || '') + '"'
			+ ' data-id="' + escapeHtml(item.id || '') + '"'
			+ ' data-name="' + escapeHtml(item.name || '') + '"'
			+ ' data-email="' + escapeHtml(item.email || '') + '"'
			+ ' data-role="' + escapeHtml(item.role || '') + '"'
			+ ' data-label="' + escapeHtml(item.label || '') + '">'
			+ escapeHtml(formatQuickItemLabel(item))
			+ '</a>';
	}

	$(document)
		.off('click.switchuserQuickSearch', '.switchuser-quick-item')
		.on('click.switchuserQuickSearch', '.switchuser-quick-item', function (e) {
		e.preventDefault();
		var $item = $(this);
		var fallbackUrl = $item.attr('href');
		$.post(toolbarConfig.ajaxUrl, {
			action: 'switchuser_quick_switch_user',
			nonce: toolbarConfig.nonce,
			user_id: $item.data('id'),
			redirect_to: toolbarConfig.currentUrl || window.location.href
		}).done(function (res) {
			if (res && res.success && res.data && res.data.redirect) {
				window.location.href = res.data.redirect;
				return;
			}
			window.location.href = fallbackUrl;
		}).fail(function () {
			window.location.href = fallbackUrl;
		});
	});

	$(document)
		.off('input.switchuserQuickSearch', '.switchuser-quick-search-input')
		.on('input.switchuserQuickSearch', '.switchuser-quick-search-input', function () {
		if (!hasToolbarConfig) {
			return;
		}
		var $input = $(this);
		var term = ($input.val() || '').toString().trim();
		var $results = $input.closest('.switchuser-front-search').find('.switchuser-quick-search-results');
		clearTimeout(searchTimer);

		if (term.length < 2) {
			$results.empty().hide();
			return;
		}

		$results.html('<div class="switchuser-quick-loading">' + ((toolbarConfig && toolbarConfig.i18n && toolbarConfig.i18n.searching) || 'Searching...') + '</div>').show();
		searchTimer = setTimeout(function () {
			$.post(toolbarConfig.ajaxUrl, {
				action: 'switchuser_search_users',
				nonce: toolbarConfig.nonce,
				term: term,
				redirect_to: toolbarConfig.currentUrl || window.location.href
			}).done(function (res) {
				if (!res || !res.success || !res.data || !res.data.results || !res.data.results.length) {
					$results.html('<div class="switchuser-quick-empty">' + ((toolbarConfig && toolbarConfig.i18n && toolbarConfig.i18n.noResults) || 'No matching users found.') + '</div>').show();
					return;
				}
				var html = '';
				$.each(res.data.results, function (_, item) {
					html += buildQuickItemHtml(item);
				});
				$results.html(html).show();
			}).fail(function () {
				$results.html('<div class="switchuser-quick-empty">' + ((toolbarConfig && toolbarConfig.i18n && toolbarConfig.i18n.noResults) || 'No matching users found.') + '</div>').show();
			});
		}, 260);
	});

	$(document)
		.off('click.switchuserQuickSearchDoc')
		.on('click.switchuserQuickSearchDoc', function (e) {
		if (!$(e.target).closest('.switchuser-front-search').length) {
			$('.switchuser-quick-search-results').hide();
		}
		if (!$(e.target).closest('#wp-admin-bar-switchuser-search, #switchuser-admin-search-popover').length) {
			closeAdminQuickSearchPopover();
		}
	});

	$(document)
		.off('keydown.switchuserQuickSearch')
		.on('keydown.switchuserQuickSearch', function (e) {
		if (e.key === 'Escape') {
			closeAdminQuickSearchPopover();
		}
	});

	$(window)
		.off('resize.switchuserQuickSearch')
		.on('resize.switchuserQuickSearch', function () {
		positionAdminQuickSearchPopover($('#switchuser-admin-search-popover'));
	});

	// ── Review notice dismiss ─────────────────────────────────────────────
	if ( typeof switchuser_notices !== 'undefined' && switchuser_notices.nonce ) {
		$( document ).off( 'click.sgReview' ).on( 'click.sgReview', '[data-sg-review-action]', function () {
			var action  = $( this ).data( 'sg-review-action' );
			var $notice = $( '#sg-review-notice' );
			$.post( switchuser_notices.ajaxUrl, {
				action:         'switchuser_dismiss_review',
				nonce:          switchuser_notices.nonce,
				dismiss_action: action,
			} ).always( function () {
				$notice.slideUp( 200 );
			} );
		} );

		// WP's native X button — treat as "Maybe Later" (14-day hide).
		$( '#sg-review-notice' ).off( 'click.sgReviewDismiss' ).on( 'click.sgReviewDismiss', '.notice-dismiss', function () {
			$.post( switchuser_notices.ajaxUrl, {
				action:         'switchuser_dismiss_review',
				nonce:          switchuser_notices.nonce,
				dismiss_action: 'later',
			} );
		} );
	}

	// ── Help dropdown ─────────────────────────────────────────────────────────
	// Use .off().on() with a namespace so double-execution (toolbar + settings
	// script handles both pointing to this file) doesn't stack duplicate handlers.
	var $helpBtn      = $( '#sg-help-btn' );
	var $helpDropdown = $( '#sg-help-dropdown' );

	$helpBtn.off( 'click.sgHelp' ).on( 'click.sgHelp', function ( e ) {
		e.stopPropagation();
		var opening = $helpDropdown.attr( 'hidden' ) !== undefined;
		if ( opening ) {
			$helpDropdown.removeAttr( 'hidden' );
			$helpBtn.addClass( 'is-open' ).attr( 'aria-expanded', 'true' );
		} else {
			$helpDropdown.attr( 'hidden', '' );
			$helpBtn.removeClass( 'is-open' ).attr( 'aria-expanded', 'false' );
		}
	} );

	$( document ).off( 'click.sgHelp' ).on( 'click.sgHelp', function () {
		$helpDropdown.attr( 'hidden', '' );
		$helpBtn.removeClass( 'is-open' ).attr( 'aria-expanded', 'false' );
	} );

	$helpDropdown.off( 'click.sgHelp' ).on( 'click.sgHelp', function ( e ) {
		e.stopPropagation();
	} );
})(jQuery);
