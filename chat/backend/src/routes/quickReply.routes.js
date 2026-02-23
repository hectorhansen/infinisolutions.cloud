'use strict';
const router = require('express').Router();
const ctrl = require('../controllers/quickReplies.controller');
const auth = require('../middlewares/auth');
const { isAdmin } = require('../middlewares/roleCheck');

router.get('/', auth, ctrl.listQuickReplies);
router.post('/', auth, isAdmin, ctrl.createQuickReply);
router.put('/:id', auth, isAdmin, ctrl.updateQuickReply);
router.delete('/:id', auth, isAdmin, ctrl.deleteQuickReply);

module.exports = router;
