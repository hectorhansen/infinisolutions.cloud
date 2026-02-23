'use strict';
const router = require('express').Router();
const ctrl = require('../controllers/conversations.controller');
const auth = require('../middlewares/auth');
const { isAdmin } = require('../middlewares/roleCheck');

router.get('/stats', auth, isAdmin, ctrl.getStats);
router.get('/', auth, ctrl.listConversations);
router.get('/:id', auth, ctrl.getConversation);
router.patch('/:id/assign', auth, isAdmin, ctrl.assignConversation);
router.patch('/:id/status', auth, ctrl.updateStatus);
router.put('/:id/read', auth, ctrl.markRead);
router.post('/:id/tags', auth, ctrl.addTag);
router.delete('/:id/tags/:tagId', auth, ctrl.removeTag);

module.exports = router;
