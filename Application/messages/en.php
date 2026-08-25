<?php
/**
 * User interface texts of the feedback form — English.
 *
 * Shared by the storefront and the backend language files, so a wording change
 * cannot be applied in one place and forgotten in the other.
 *
 * @package tabslFeedback
 **/

return [
    // Controls
    'TABSLFEEDBACK_TRIGGER' => 'Feedback',
    'TABSLFEEDBACK_TITLE' => 'Send feedback',
    'TABSLFEEDBACK_MESSAGE_LABEL' => 'Message',
    'TABSLFEEDBACK_SUBJECT_LABEL' => 'Subject',
    'TABSLFEEDBACK_SCREENSHOTS_LABEL' => 'Screenshots',
    'TABSLFEEDBACK_SCREENSHOTS_HINT' => 'Paste an image from the clipboard with Ctrl+V or ⌘+V. Several images are possible.',
    'TABSLFEEDBACK_NAME_LABEL' => 'Name (optional)',
    'TABSLFEEDBACK_EMAIL_LABEL' => 'Email (optional)',
    'TABSLFEEDBACK_SUBMIT' => 'Send',
    'TABSLFEEDBACK_CANCEL' => 'Cancel',
    'TABSLFEEDBACK_CLOSE' => 'Close',
    'TABSLFEEDBACK_SENDING' => 'Sending …',
    'TABSLFEEDBACK_REMOVE_IMAGE' => 'Remove image',

    // Responses
    'TABSLFEEDBACK_THANKS' => 'Thank you for your feedback.',
    'TABSLFEEDBACK_ERROR_GENERIC' => 'Your feedback could not be submitted right now. Please try again later.',
    'TABSLFEEDBACK_ERROR_NETWORK' => 'The connection was interrupted. Please try again.',
    'TABSLFEEDBACK_ERROR_BOT_CHECK' => 'The security check was not passed. Please try again.',

    // Input validation
    'TABSLFEEDBACK_ERROR_MESSAGE_REQUIRED' => 'Please enter a message.',
    'TABSLFEEDBACK_ERROR_MESSAGE_TOO_LONG' => 'The message is too long (%d characters at most).',
    'TABSLFEEDBACK_ERROR_EMAIL_INVALID' => 'Please check the email address you entered.',
    'TABSLFEEDBACK_ERROR_CONTACT_TOO_LONG' => 'Name and email may be %d characters long at most.',
    'TABSLFEEDBACK_ERROR_SUBJECT_TOO_LONG' => 'The subject is too long (%d characters at most).',
    'TABSLFEEDBACK_ERROR_TOO_MANY_IMAGES' => 'A maximum of %d images is possible.',
    'TABSLFEEDBACK_ERROR_IMAGE_TOO_LARGE' => 'One image is too large (%d MB per image at most).',
    'TABSLFEEDBACK_ERROR_IMAGES_TOO_LARGE' => 'The images are too large in total (%d MB at most).',
    'TABSLFEEDBACK_ERROR_IMAGE_TYPE' => 'This image format is not supported. PNG, JPG, GIF and WebP are possible.',
];
