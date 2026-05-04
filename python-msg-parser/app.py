from fastapi import FastAPI, UploadFile, File, HTTPException
from fastapi.middleware.cors import CORSMiddleware
import extract_msg
import json
import tempfile
import os
from typing import Dict, Any, List
from pydantic import BaseModel
from datetime import datetime
import email.utils
import logging

# Configure logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

app = FastAPI(title="MSG Parser Service", version="1.0.0")

# Configure CORS
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],  # Configure properly in production
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

class EmailAttachment(BaseModel):
    name: str
    type: str
    size: int
    content_id: str = None

class ParsedEmail(BaseModel):
    subject: str = ""
    sender: str = ""
    to: List[str] = []
    cc: List[str] = []
    bcc: List[str] = []
    date: str = ""
    body: str = ""
    html_body: str = ""
    attachments: List[EmailAttachment] = []
    headers: Dict[str, str] = {}
    parsed_successfully: bool = True
    error: str = None

def parse_msg_file(file_path: str) -> ParsedEmail:
    """Parse .msg file using extract-msg library"""
    try:
        msg = extract_msg.Message(file_path)

        # Extract basic info
        subject = msg.subject or ""
        sender = msg.sender or ""

        # Extract recipients
        to_recipients = msg.to or ""
        cc_recipients = msg.cc or ""
        bcc_recipients = msg.bcc or ""

        # Parse date
        date_str = ""
        if msg.date:
            try:
                # Try to parse the date
                dt_tuple = email.utils.parsedate_tz(msg.date)
                if dt_tuple:
                    dt = datetime.fromtimestamp(email.utils.mktime_tz(dt_tuple))
                    date_str = dt.isoformat()
                else:
                    date_str = msg.date
            except:
                date_str = msg.date

        # Extract body
        body = msg.body or ""

        # Extract HTML body if available
        html_body = ""
        if hasattr(msg, 'htmlBody') and msg.htmlBody:
            html_body = msg.htmlBody

        # Extract attachments
        attachments = []
        for att in msg.attachments:
            attachment = EmailAttachment(
                name=att.longFilename or att.filename or f"attachment_{att.cid}",
                type=att.mimetype or "application/octet-stream",
                size=len(att.data) if att.data else 0,
                content_id=att.cid
            )
            attachments.append(attachment)

        # Extract headers
        headers = {}
        if hasattr(msg, 'header') and msg.header:
            for key, value in msg.header.items():
                if isinstance(value, list):
                    headers[key] = ", ".join(str(v) for v in value)
                else:
                    headers[key] = str(value)

        msg.close()

        return ParsedEmail(
            subject=subject,
            sender=sender,
            to=[r.strip() for r in to_recipients.split(';') if r.strip()],
            cc=[r.strip() for r in cc_recipients.split(';') if r.strip()],
            bcc=[r.strip() for r in bcc_recipients.split(';') if r.strip()],
            date=date_str,
            body=body,
            html_body=html_body,
            attachments=attachments,
            headers=headers,
            parsed_successfully=True
        )

    except Exception as e:
        logger.error(f"Failed to parse MSG file: {str(e)}")
        return ParsedEmail(
            parsed_successfully=False,
            error=str(e)
        )

@app.post("/parse-msg", response_model=ParsedEmail)
async def parse_msg(file: UploadFile = File(...)):
    """Parse uploaded .msg file"""
    if not file.filename.lower().endswith('.msg'):
        raise HTTPException(status_code=400, detail="File must be a .msg file")

    # Save uploaded file to temp location
    with tempfile.NamedTemporaryFile(delete=False, suffix='.msg') as tmp:
        content = await file.read()
        tmp.write(content)
        tmp_path = tmp.name

    try:
        # Parse the file
        result = parse_msg_file(tmp_path)

        # Clean up temp file
        os.unlink(tmp_path)

        return result

    except Exception as e:
        # Clean up temp file in case of error
        if os.path.exists(tmp_path):
            os.unlink(tmp_path)
        logger.error(f"Error processing file: {str(e)}")
        raise HTTPException(status_code=500, detail=f"Failed to parse MSG file: {str(e)}")

@app.get("/health")
async def health_check():
    """Health check endpoint"""
    return {"status": "healthy", "service": "msg-parser"}

if __name__ == "__main__":
    import uvicorn
    uvicorn.run(app, host="0.0.0.0", port=8000)
