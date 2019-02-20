package replaying

type ReplayedSession struct {
	SessionId       string
	CallFromInbound *CallFromInbound
	ReturnInbound   *ReturnInbound
	Actions         []ReplayedAction
}
