'use strict';
const router = require('express').Router();
const ctrl = require('../controllers/webhook.controller');

router.get('/', ctrl.verifyWebhook);
router.post('/', ctrl.receiveWebhook);

module.exports = router;
