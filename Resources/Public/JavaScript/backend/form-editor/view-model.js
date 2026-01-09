/**
 * Module: @brezo-it/multi-file-upload/backend/form-editor/view-model
 */

export function bootstrap(formEditorApp) {
    const subscriberIdentifier = 'view/stage/abstract/render/template/perform';

    formEditorApp.getPublisherSubscriber().subscribe(subscriberIdentifier, function(topic, args) {
        const elementType = args[0] && args[0].get('type');
        if (elementType === 'MultiImageUpload' || elementType === 'MultiFileUpload') {
            formEditorApp.getViewModel().getStage().renderFileUploadTemplates(args[0], args[1]);
        }
    });
}
