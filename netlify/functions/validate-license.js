/**
 * GMCQ License Server - Netlify Function
 * Deploy this to your Netlify Functions to enable remote license validation
 */

const jwt = require('jsonwebtoken');
const crypto = require('crypto');

// Store your valid license key hashes here (SHA256)
// Generate with: echo -n "YOUR_KEY" | shasum -a 256
const VALID_LICENSES = process.env.VALID_LICENSES?.split(',') || [];

exports.handler = async (event, context) => {
  // Only allow POST requests
  if (event.httpMethod !== 'POST') {
    return {
      statusCode: 405,
      body: JSON.stringify({ error: 'Method not allowed' })
    };
  }

  try {
    const { license_key, domain } = JSON.parse(event.body || '{}');

    // Validate required fields
    if (!license_key || !domain) {
      return {
        statusCode: 400,
        body: JSON.stringify({
          valid: false,
          message: 'License key and domain are required.'
        })
      };
    }

    // Hash the provided license key
    const hashedKey = crypto
      .createHash('sha256')
      .update(license_key)
      .digest('hex');

    // Check if license is valid
    const isValid = VALID_LICENSES.includes(hashedKey);

    if (!isValid) {
      return {
        statusCode: 403,
        body: JSON.stringify({
          valid: false,
          message: 'Invalid license key.'
        })
      };
    }

    // Get JWT secret from environment
    const jwtSecret = process.env.JWT_SECRET;
    if (!jwtSecret) {
      return {
        statusCode: 500,
        body: JSON.stringify({
          valid: false,
          message: 'Server configuration error.'
        })
      };
    }

    // Generate signed token (30 days expiry)
    const token = jwt.sign(
      {
        domain,
        license_hash: hashedKey,
        iat: Math.floor(Date.now() / 1000)
      },
      jwtSecret,
      { expiresIn: '365d' }
    );

    return {
      statusCode: 200,
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        valid: true,
        token,
        expires_in: 31536000
      })
    };
  } catch (error) {
    console.error('License validation error:', error);
    return {
      statusCode: 500,
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        valid: false,
        message: 'Server error.'
      })
    };
  }
};