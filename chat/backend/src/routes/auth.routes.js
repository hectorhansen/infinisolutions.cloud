'use strict';
const router = require('express').Router();
const ctrl = require('../controllers/auth.controller');
const auth = require('../middlewares/auth');

router.post('/login', ctrl.login);
router.post('/refresh', ctrl.refresh);
router.post('/logout', auth, ctrl.logout);
router.get('/me', auth, ctrl.me);
router.patch('/status', auth, ctrl.updateStatus);

module.exports = router;
