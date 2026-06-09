const jwt = require('jsonwebtoken');
const crypto = require('crypto');

const VALID_LICENSES = (process.env.VALID_LICENSES || '').split(',').filter(Boolean);

module.exports = function handler(req, res) {
  if (req.method !== 'POST') {
    res.status(405).json({ error: 'Method not allowed' });
    return;
  }

  try {
    const { license_key, domain } = req.body || {};

    if (!license_key || !domain) {
      res.status(400).json({
        valid: false,
        message: 'License key and domain are required.'
      });
      return;
    }

    const hashedKey = crypto
      .createHash('sha256')
      .update(license_key)
      .digest('hex');

    const isValid = VALID_LICENSES.map(l => l.toLowerCase()).includes(hashedKey.toLowerCase());

    if (!isValid) {
      res.status(403).json({
        valid: false,
        message: 'Invalid license key.'
      });
      return;
    }

    const jwtSecret = process.env.JWT_SECRET;
    if (!jwtSecret) {
      res.status(500).json({
        valid: false,
        message: 'Server configuration error.'
      });
      return;
    }

    const token = jwt.sign(
      {
        domain,
        license_hash: hashedKey,
        iat: Math.floor(Date.now() / 1000)
      },
      jwtSecret,
      { expiresIn: '365d' }
    );

    res.status(200).json({
      valid: true,
      token,
      expires_in: 31536000
    });
  } catch (error) {
    console.error('License validation error:', error);
    res.status(500).json({
      valid: false,
      message: 'Server error.'
    });
  }
};
