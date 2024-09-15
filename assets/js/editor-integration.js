/**
 * WPAI Content Generator JavaScript
 *
 * @package WPAIContentGenerator
 */

(function($) {
    'use strict';

    function initWPAIContentGenerator() {
        const $generateButton = $('#wpai_generate_button');
        const $promptInput = $('#wpai_prompt');
        const $loadingIndicator = $('#wpai_loading');

        $generateButton.on('click', handleGenerateClick);

        function handleGenerateClick(event) {
            event.preventDefault();
            const prompt = $promptInput.val().trim();

            if (!prompt) {
                alert(wpai_ajax.i18n.empty_prompt);
                return;
            }

            $loadingIndicator.show();
            generateContent(prompt);
        }

        function generateContent(prompt) {
            $.ajax({
                url: wpai_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'wpai_generate_content',
                    prompt: prompt,
                    post_id: $('#post_ID').val(),
                    nonce: wpai_ajax.nonce
                },
                success: handleAjaxSuccess,
                error: handleAjaxError,
                complete: () => $loadingIndicator.hide()
            });
        }

        function handleAjaxSuccess(response) {
            if (response.success) {
                updateEditor(response.data);
            } else {
                alert(wpai_ajax.i18n.generation_error + response.data.message);
            }
        }

        function handleAjaxError(jqXHR, textStatus, errorThrown) {
            console.error('AJAX error:', {
                status: jqXHR.status,
                statusText: jqXHR.statusText,
                responseText: jqXHR.responseText,
                textStatus: textStatus,
                errorThrown: errorThrown
            });
            alert(wpai_ajax.i18n.server_error);
        }
    }
    /**
     * Update the editor with generated content.
     * @param {Object} data - The generated content data.
     */
    function updateEditor(data) {
        if (isGutenbergActive()) {
            updateGutenbergEditor(data);
        } else {
            updateClassicEditor(data);
        }
    }

    /**
     * Check if Gutenberg editor is active.
     * @return {boolean} True if Gutenberg is active, false otherwise.
     */
    function isGutenbergActive() {
        return (
            typeof wp !== 'undefined' &&
            wp.hasOwnProperty('blocks') &&
            wp.data &&
            wp.data.select('core/editor') &&
            wp.data.select('core/edit-post')
        );
    }

    /**
     * Update Gutenberg editor with generated content.
     * @param {Object} data - The generated content data.
     */
    function updateGutenbergEditor(data) {
        const { select, dispatch } = wp.data;
        const editor = select('core/editor');
        const editorDispatcher = dispatch('core/editor');
        
        if (editor && editorDispatcher) {
            try {
                editorDispatcher.editPost({
                    title: data.title,
                    content: data.content,
                    excerpt: data.excerpt
                });
            } catch (error) {
                console.error('Error updating Gutenberg editor:', error);
                updateClassicEditor(data);
            }
        } else {
            console.error('Gutenberg editor API not fully available');
            updateClassicEditor(data);
        }
    }

    /**
     * Update Classic editor with generated content.
     * @param {Object} data - The generated content data.
     */
    function updateClassicEditor(data) {
        $('#title').val(data.title);
        
        if (typeof tinyMCE !== 'undefined' && tinyMCE.get('content')) {
            tinyMCE.get('content').setContent(data.content);
        } else {
            $('#content').val(data.content);
        }
        
        $('#excerpt').val(data.excerpt);
    }

    // Initialize when the document is ready
    $(document).ready(initWPAIContentGenerator);

})(jQuery);