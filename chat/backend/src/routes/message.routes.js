'use strict';
const router = require('express').Router({ mergeParams: true });
const ctrl = require('../controllers/messages.controller');
const auth = require('../middlewares/auth');
const upload = require('../middlewares/upload');

router.get('/:conversationId', auth, ctrl.listMessages);
router.post('/:conversationId/text', auth, ctrl.sendText);
router.post('/:conversationId/media', auth, upload.single('file'), ctrl.sendMedia);

module.exports = router;
