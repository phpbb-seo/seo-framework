/**
 * phpBB SEO Framework - Lightweight Admin Interactive Helpers
 * Strictly Vanilla JavaScript - No external dependencies.
 */
document.addEventListener('DOMContentLoaded', function () {
	'use strict';

	// 1. Variable Chip Click -> Insert token at caret position
	var chips = document.querySelectorAll('.pseo-chip');
	chips.forEach(function (chip) {
		chip.addEventListener('click', function (e) {
			e.preventDefault();
			var targetId = this.getAttribute('data-target');
			var token = this.getAttribute('data-token');
			var input = document.getElementById(targetId);

			if (input && token) {
				var start = input.selectionStart || input.value.length;
				var end = input.selectionEnd || input.value.length;
				var val = input.value;
				input.value = val.substring(0, start) + token + val.substring(end);
				input.focus();
				var newPos = start + token.length;
				if (input.setSelectionRange) {
					input.setSelectionRange(newPos, newPos);
				}
				// Trigger input event to update live previews and dirty state
				var event = new Event('input', { bubbles: true });
				input.dispatchEvent(event);
			}
		});
	});

	// 2. Real-time Live Preview Helper
	function updatePreview(inputId, previewId, sampleReplacements) {
		var input = document.getElementById(inputId);
		var preview = document.getElementById(previewId);
		if (!input || !preview) return;

		var handler = function () {
			var text = input.value;
			for (var key in sampleReplacements) {
				if (sampleReplacements.hasOwnProperty(key)) {
					var regex = new RegExp('\\{' + key + '\\}', 'g');
					text = text.replace(regex, sampleReplacements[key]);
				}
			}
			// Clean any remaining {token}
			text = text.replace(/\{[a-z0-9_\-]+\}/gi, '');
			text = text.replace(/\s*([\-\|\—\–])\s*(\1\s*)+/g, ' $1 ');
			text = text.replace(/^\s*[\-\|\—\–]\s*/, '').replace(/\s*[\-\|\—\–]\s*$/, '');
			preview.textContent = text.trim();
		};

		input.addEventListener('input', handler);
	}

	// Permalinks Live Previews
	updatePreview('pattern_forum', 'preview_forum', { slug: 'sample-forum', id: '12', page: '2' });
	updatePreview('pattern_topic', 'preview_topic', { slug: 'sample-topic', id: '345', page: '2' });
	updatePreview('pattern_member', 'preview_member', { slug: 'sample-user', id: '27' });
	updatePreview('pattern_group', 'preview_group', { slug: 'sample-group', id: '5' });

	// Titles & Meta Live Previews
	updatePreview('home_title', 'preview_home_title', { board_name: 'My Board', site_desc: 'A vibrant community', page: '' });
	updatePreview('home_desc', 'preview_home_desc', { board_name: 'My Board', site_desc: 'A vibrant community' });
	updatePreview('forum_title', 'preview_forum_title', { forum_name: 'General Discussion', forum_id: '2', board_name: 'My Board', page: '' });
	updatePreview('topic_title', 'preview_topic_title', { topic_title: 'Sample Topic', topic_id: '4', forum_name: 'General Discussion', board_name: 'My Board', page: '' });
	updatePreview('member_title', 'preview_member_title', { username: 'admin', user_id: '2', board_name: 'My Board' });

	// 3. Form Dirty State Tracking
	var forms = document.querySelectorAll('#pseo_permalinks_form, #pseo_titles_meta_form');
	forms.forEach(function (form) {
		var indicator = form.querySelector('#pseo_status_indicator');
		if (indicator) {
			form.addEventListener('input', function () {
				indicator.style.display = 'inline';
			});
		}
	});
});
