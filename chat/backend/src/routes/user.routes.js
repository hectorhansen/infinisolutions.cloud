'use strict';
const router = require('express').Router();
const ctrl = require('../controllers/users.controller');
const auth = require('../middlewares/auth');
const { isAdmin } = require('../middlewares/roleCheck');

router.get('/', auth, isAdmin, ctrl.listUsers);
router.post('/', auth, isAdmin, ctrl.createUser);
router.get('/:id', auth, isAdmin, ctrl.getUser);
router.put('/:id', auth, isAdmin, ctrl.updateUser);
router.delete('/:id', auth, isAdmin, ctrl.deleteUser);

module.exports = router;
