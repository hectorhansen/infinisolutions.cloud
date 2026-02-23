'use strict';
const router = require('express').Router();
const ctrl = require('../controllers/settings.controller');
const auth = require('../middlewares/auth');
const { isAdmin } = require('../middlewares/roleCheck');

router.get('/', auth, isAdmin, ctrl.getSettings);
router.put('/', auth, isAdmin, ctrl.updateSettings);

module.exports = router;
