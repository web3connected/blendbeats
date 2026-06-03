import type { Request, Response } from "express";

export default function healthGet(_req: Request, res: Response) {
	res.status(200).json({ ok: true });
}
