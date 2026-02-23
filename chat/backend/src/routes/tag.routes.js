'use strict';
const router = require('express').Router();
const ctrl = require('../controllers/tags.controller');
const auth = require('../middlewares/auth');
const { isAdmin } = require('../middlewares/roleCheck');

router.get('/', auth, ctrl.listTags);
router.post('/', auth, isAdmin, ctrl.createTag);
router.put('/:id', auth, isAdmin, ctrl.updateTag);
router.delete('/:id', auth, isAdmin, ctrl.deleteTag);

module.exports = router;
